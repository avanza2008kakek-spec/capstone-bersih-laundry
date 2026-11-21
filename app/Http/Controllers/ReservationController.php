<?php

namespace App\Http\Controllers;

use App\Models\Customer;                                        // Model pelanggan
use App\Models\Reservation;                                     // Reservasi (muncul sebagai notifikasi di Staff)
use App\Models\Service;                                         // Model layanan (untuk hitung perkiraan harga)
use App\Services\MapsLinkResolver;                              // Buka link pendek Google Maps → titik koordinat
use App\Support\Phone;                                          // Helper format nomor HP
use App\Support\PickupFee;                                      // Hitung ongkos jemput dari jarak
use App\Support\Settings;                                       // Nama laundry
use Illuminate\Http\Request;                                    // Penangan request HTTP

/**
 * Reservasi laundry dari halaman depan (khusus pelanggan yang sudah masuk).
 * Alamat & lokasi Google Maps disimpan ke data pelanggan (menjadi alamat default jika keduanya terisi),
 * lalu pesanan diteruskan ke WhatsApp admin.
 */
class ReservationController extends Controller
{
    public function store(Request $request, Settings $settings, PickupFee $pickupFee, MapsLinkResolver $resolver)
    {
        // 1. Wajib sudah masuk; kalau belum, arahkan ke Masuk lalu kembali ke form reservasi
        $customer = Customer::find($request->session()->get('customer_id'));
        if (! $customer) {
            $request->session()->put('after_login', route('customer.home') . '#pesan');
            return redirect()->route('login.form')->with('info', 'Silakan masuk dulu untuk reservasi laundry.');
        }

        // 2. Pakai alamat default jika pelanggan memilihnya dan alamat default memang lengkap;
        //    kalau tidak, alamat & lokasi wajib dicek dari isian form
        $useDefault = $request->boolean('use_default_address') && $customer->hasDefaultAddress();
        $rules = [
            'service'          => ['required', 'string', 'max:100'],
            'estimated_weight' => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
            'pickup_date'      => ['required', 'date', 'after_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:200'],
        ] + ($useDefault ? [] : Customer::addressRules(false));

        $validator = validator($request->all(), $rules, Customer::addressMessages(), [
            'service' => 'layanan', 'estimated_weight' => 'perkiraan berat',
            'pickup_date' => 'tanggal jemput', 'notes' => 'catatan',
        ]);
        if ($validator->fails()) {
            // Kembali ke bagian reservasi (#pesan) beserta pesan errornya
            return redirect()->to(route('customer.home') . '#pesan')->withErrors($validator)->withInput();
        }
        $data = $validator->validated();

        // 3. Simpan alamat & lokasi baru; jika keduanya terisi, otomatis menjadi alamat default
        if (! $useDefault) {
            $customer->update([
                'address'  => trim($data['address']),
                'maps_url' => ($data['maps_url'] ?? null) ?: $customer->maps_url, // Lokasi kosong → lokasi lama tetap dipakai
            ]);
        }
        // Link pendek (maps.app.goo.gl) dibuka dulu supaya ongkos jemput bisa langsung dihitung dari jaraknya
        if ($customer->maps_url && ! PickupFee::coords($customer->maps_url) && ($full = $resolver->resolve($customer->maps_url))) {
            $customer->update(['maps_url' => $full]);
        }

        $fee = PickupFee::describe($pickupFee->forCustomerUrl($customer->maps_url)); // Perkiraan dari jarak garis lurus

        // Hitung perkiraan berat & harga dari tarif layanan di database (jika pelanggan mengisi perkiraan berat)
        $weightVal = isset($data['estimated_weight']) && $data['estimated_weight'] !== '' ? (float) $data['estimated_weight'] : null;
        $weightText = null;
        $priceText = null;
        if ($weightVal && $weightVal > 0) {
            $svc = null;
            try {
                $svc = Service::where('is_active', 1)->where('name', $data['service'])->first();
            } catch (\Illuminate\Database\QueryException $e) {
            }
            $unit = $svc?->unit ?: 'kg';
            $formattedWeight = rtrim(rtrim(number_format($weightVal, 2, ',', '.'), '0'), ',');
            $weightText = $formattedWeight . ' ' . $unit;
            $priceText = $svc
                ? 'Rp' . number_format((int) round($weightVal * (float) $svc->price), 0, ',', '.')
                : 'dihitung admin';
        }

        // 4. Simpan reservasi → muncul sebagai notifikasi "Reservasi baru" di Staff.
        //    Jika tabel belum dibuat di server, reservasi tetap diteruskan ke WhatsApp (error dicatat di log).
        $reservationData = [
            'customer_id'      => $customer->id,
            'service'          => $data['service'],
            'estimated_weight' => $weightText,
            'estimated_price'  => $priceText,
            'pickup_date'      => $data['pickup_date'],
            'address'          => $customer->address,
            'maps_url'         => $customer->maps_url,
            'pickup_fee'       => $fee,
            'notes'            => trim((string) ($data['notes'] ?? '')) ?: null,
            'status'           => 'new',
        ];
        try {
            Reservation::create($reservationData);
        } catch (\Illuminate\Database\QueryException $e) {
            try {
                unset($reservationData['estimated_weight'], $reservationData['estimated_price']);
                Reservation::create($reservationData);
            } catch (\Illuminate\Database\QueryException $e2) {
                report($e2);
            }
        }

        // 5. Susun pesan WhatsApp untuk admin, satu data per baris
        $lines = [
            'Halo ' . $settings->businessName() . ', saya mau reservasi laundry:',
            'Nama: ' . $customer->name,
            'No. WA: ' . Phone::toLocal(Phone::normalize($customer->phone) ?? $customer->phone),
            'Alamat: ' . $customer->address,
            'Lokasi: ' . ($customer->maps_url ?: '-'),
            'Ongkos jemput: ' . $fee,
            'Layanan: ' . $data['service'],
        ];
        if ($weightText) {
            $lines[] = 'Perkiraan berat: ' . $weightText;
            $lines[] = 'Perkiraan harga: ' . $priceText;
        }
        $lines[] = 'Tanggal jemput: ' . date('d-m-Y', strtotime($data['pickup_date']));
        $lines[] = 'Catatan: ' . (trim((string) ($data['notes'] ?? '')) ?: '-');

        // 6. Buka WhatsApp admin dengan pesan yang sudah terisi
        return redirect()->away('https://wa.me/' . config('services.laundry.wa_admin') . '?text=' . rawurlencode(implode("\n", $lines)));
    }
}
