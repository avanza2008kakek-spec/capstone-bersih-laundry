<?php

namespace App\Http\Controllers;

use App\Models\Customer;                                        // Model pelanggan
use App\Services\MapsLinkResolver;                             // Buka link pendek Google Maps → titik koordinat
use App\Services\ReverseGeocoder;                               // Ubah titik GPS menjadi alamat teks
use Illuminate\Http\Request;                                    // Penangan request HTTP

/**
 * Menu Edit Alamat pelanggan (/akun/alamat): ubah alamat default & lokasi Google Maps.
 * Khusus pelanggan yang sudah masuk dengan nomor WhatsApp.
 */
class AddressController extends Controller
{
    // Tujuan kembali yang diterima lewat ?next= (bukan alamat bebas, supaya tidak bisa dialihkan ke situs lain)
    private const NEXT_TARGETS = ['reservasi'];

    // 1. Form edit alamat
    public function edit(Request $request)
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->toLogin($request);
        }

        return view('customer.address', [
            'customer' => $customer,
            'next'     => in_array($request->query('next'), self::NEXT_TARGETS, true) ? $request->query('next') : null,
        ]);
    }

    // 2. Simpan alamat & lokasi sebagai alamat default
    public function update(Request $request, MapsLinkResolver $resolver)
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->toLogin($request);
        }

        // Di halaman ini alamat & lokasi sama-sama wajib, karena hasilnya menjadi alamat default
        $data = $request->validate(Customer::addressRules(true), Customer::addressMessages());

        $customer->update([
            'address'  => trim($data['address']),
            'maps_url' => $resolver->resolve($data['maps_url']) ?? $data['maps_url'], // Link pendek diubah ke link berkoordinat (untuk ongkos jemput)
        ]);

        // Datang dari form reservasi → kembali ke sana; selain itu tetap di halaman ini
        if ($request->input('next') === 'reservasi') {
            return redirect()->to(route('customer.home') . '#pesan');
        }
        return redirect()->route('address.edit')->with('status', 'Alamat default berhasil disimpan.');
    }

    // 3. Tombol "Gunakan lokasi saya": ubah titik GPS menjadi alamat teks (dipanggil JavaScript, jawaban JSON)
    public function fromLocation(Request $request, ReverseGeocoder $geocoder)
    {
        // Boleh dipakai pelanggan yang sudah masuk, atau staf yang login (owner mengatur lokasi laundry)
        if (! $this->customer($request) && ! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Silakan masuk dulu.'], 401);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],   // Lintang
            'lng' => ['required', 'numeric', 'between:-180,180'], // Bujur
        ]);

        return response()->json($geocoder->lookup((float) $data['lat'], (float) $data['lng']));
    }

    // 4. Kotak "Cari" di peta pilih titik: cari jalan/tempat, jawab daftar hasil + koordinat (JSON)
    public function searchPlace(Request $request, ReverseGeocoder $geocoder)
    {
        // Boleh dipakai pelanggan yang sudah masuk, atau staf yang login (owner mengatur lokasi laundry)
        if (! $this->customer($request) && ! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Silakan masuk dulu.'], 401);
        }

        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);

        return response()->json($geocoder->search($data['q']));
    }

    // 5. Link pendek Google Maps yang ditempel → link berkoordinat (supaya pratinjau peta & ongkos jemput langsung muncul)
    public function resolveLink(Request $request, MapsLinkResolver $resolver)
    {
        if (! $this->customer($request) && ! $request->user()) {
            return response()->json(['ok' => false, 'error' => 'Silakan masuk dulu.'], 401);
        }

        $data = $request->validate(['url' => ['required', 'string', 'max:500', 'regex:' . Customer::MAPS_URL_PATTERN]]);
        $url = $resolver->resolve($data['url']);

        return $url
            ? response()->json(['ok' => true, 'url' => $url])
            : response()->json(['ok' => false, 'error' => 'Titik lokasi dari link ini tidak terbaca. Gunakan tombol "Pilih di peta".']);
    }

    // ---------- Fungsi bantu ----------

    private function customer(Request $request): ?Customer
    {
        return Customer::find($request->session()->get('customer_id'));
    }

    // Belum masuk → ke halaman Masuk, setelah itu kembali ke Edit Alamat
    private function toLogin(Request $request)
    {
        $request->session()->put('after_login', route('address.edit'));
        return redirect()->route('login.form')->with('info', 'Silakan masuk dulu untuk mengubah alamat.');
    }
}
