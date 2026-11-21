<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Customer;                                        // Aturan isian alamat & link Google Maps
use App\Services\MapsLinkResolver;                              // Buka link pendek Google Maps → titik koordinat
use App\Services\VerificationHub;                               // Layanan pengirim kode verifikasi WA (OTP)
use App\Support\Phone;                                          // Helper format nomor WhatsApp
use App\Support\PhotoCompressor;                                // Perkecil gambar QRIS agar ringan di nota & struk
use App\Support\PickupFee;                                      // Baca koordinat dari link Google Maps
use App\Support\Settings;                                       // Pengaturan usaha
use App\Support\Themes;                                         // Pilihan tema warna
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Validation\Rule;                                 // Aturan validasi

/**
 * Menu owner → Pengaturan Usaha: nama laundry, lokasi laundry, ongkos jemput (bertingkat per km), rekening & QRIS, verifikasi WA (OTP), dan tema web.
 */
class SettingController extends Controller
{
    public function __construct(private Settings $settings)
    {
    }

    // Halaman pengaturan
    public function edit()
    {
        return view('kasir.settings', [
            'currentName'            => $this->settings->businessName(),
            'defaultName'            => Settings::DEFAULTS['business_name'],
            'shopAddress'            => $this->settings->get('shop_address'),
            'shopMapsUrl'            => $this->settings->get('shop_maps_url'),
            'rates'                  => $this->settings->pickupRates(),
            'bankAccounts'           => $this->settings->bankAccounts(),
            'qrisImage'              => $this->settings->qrisImage(),
            'verification'           => $this->settings->verificationConfig(),
            'defaultVerificationUrl' => (string) config('services.verification_hub.url', 'https://posaja.kantorposbekasi.com/api/kirim-verifikasi.php'),
            'themeKey'               => $this->settings->themeKey(),
        ]);
    }

    // 1. Nama laundry
    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:40'], // Maks. 40 huruf agar muat di navbar & pesan WA
        ], [], ['business_name' => 'nama laundry']);

        $this->settings->set(['business_name' => trim($data['business_name'])]);

        return redirect()->route('kasir.settings')->with('status', 'Nama laundry berhasil diubah menjadi "' . trim($data['business_name']) . '".');
    }

    // 2. Lokasi laundry (titik di peta wajib, karena dipakai menghitung jarak jemput)
    public function updateLocation(Request $request, MapsLinkResolver $resolver)
    {
        $data = $request->validate(Customer::addressRules(true), Customer::addressMessages());
        $data['maps_url'] = $resolver->resolve($data['maps_url']) ?? $data['maps_url']; // Link pendek → link berkoordinat

        if (! PickupFee::coords($data['maps_url'])) {
            return redirect()->to(route('kasir.settings') . '#lokasi')->withInput()->withErrors([
                'maps_url' => 'Link lokasi harus berisi titik koordinat. Gunakan tombol "Pilih di peta" atau "Gunakan lokasi saya".',
            ]);
        }

        $this->settings->set(['shop_address' => trim($data['address']), 'shop_maps_url' => $data['maps_url']]);

        return redirect()->to(route('kasir.settings') . '#lokasi')->with('status', 'Lokasi laundry disimpan.');
    }

    // 3. Ongkos jemput bertingkat: tiap baris = "sampai X km → Rp Y"
    public function updateRates(Request $request)
    {
        // Baris kosong (km & harga sama-sama kosong) diabaikan
        $rows = collect($request->input('rates', []))
            ->filter(fn ($r) => filled($r['max_km'] ?? null) || filled($r['price'] ?? null))
            ->values()->all();
        $request->merge(['rates' => $rows]);

        $request->validate([
            'rates'          => ['array', 'max:20'],
            'rates.*.max_km' => ['required', 'numeric', 'gt:0', 'max:500', 'distinct'],   // Jarak tidak boleh dobel
            'rates.*.price'  => ['required', 'integer', 'min:0', 'max:10000000'],
        ], [
            'rates.*.max_km.distinct' => 'Ada jarak yang sama dua kali.',
        ], ['rates.*.max_km' => 'jarak (km)', 'rates.*.price' => 'ongkos']);

        $rates = collect($rows)
            ->map(fn ($r) => ['max_km' => round((float) $r['max_km'], 1), 'price' => (int) $r['price']])
            ->sortBy('max_km')->values()->all();
        $this->settings->set(['pickup_rates' => $rates]);

        return redirect()->to(route('kasir.settings') . '#ongkos')->with('status', count($rates) ? 'Tarif jemput disimpan.' : 'Tarif jemput dikosongkan (ongkos dihitung admin).');
    }

    // 4. Data rekening transfer & gambar QRIS (untuk ditampilkan di nota yang belum bayar / DP)
    public function updatePayment(Request $request)
    {
        // Baris rekening yang semua kolomnya kosong diabaikan
        $rows = collect($request->input('accounts', []))
            ->filter(fn ($r) => filled($r['bank'] ?? null) || filled($r['number'] ?? null) || filled($r['holder'] ?? null))
            ->values()->all();
        $request->merge(['accounts' => $rows]);

        $request->validate([
            'accounts'          => ['array', 'max:10'],
            'accounts.*.bank'   => ['required', 'string', 'max:50'],
            'accounts.*.number' => ['required', 'string', 'max:50'],
            'accounts.*.holder' => ['required', 'string', 'max:100'],
            'qris_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'accounts.*.bank.required'   => 'Nama bank wajib diisi.',
            'accounts.*.number.required' => 'Nomor rekening wajib diisi.',
            'accounts.*.holder.required' => 'Nama pemilik rekening (atas nama) wajib diisi.',
        ], [
            'accounts.*.bank'   => 'nama bank',
            'accounts.*.number' => 'nomor rekening',
            'accounts.*.holder' => 'atas nama',
            'qris_image'        => 'gambar QRIS',
        ]);

        $accounts = collect($rows)->map(fn ($r) => [
            'bank'   => trim((string) $r['bank']),
            'number' => trim((string) $r['number']),
            'holder' => trim((string) $r['holder']),
        ])->values()->all();

        $updates = ['bank_accounts' => $accounts];

        if ($request->hasFile('qris_image')) {
            $compressed = PhotoCompressor::compress($request->file('qris_image'));
            if (! $compressed) {
                return redirect()->to(route('kasir.settings') . '#pembayaran')->withInput()->withErrors([
                    'qris_image' => 'Gambar QRIS terlalu besar dan tidak bisa diperkecil. Gunakan gambar yang lebih kecil.',
                ]);
            }
            $updates['qris_image'] = 'data:' . $compressed['mime'] . ';base64,' . base64_encode($compressed['data']);
        }

        $this->settings->set($updates);

        return redirect()->to(route('kasir.settings') . '#pembayaran')->with('status', 'Data rekening dan pengaturan QRIS berhasil disimpan.');
    }

    // 4b. Hapus gambar QRIS
    public function destroyQris()
    {
        $this->settings->set(['qris_image' => '']);

        return redirect()->to(route('kasir.settings') . '#pembayaran')->with('status', 'Gambar QRIS dihapus.');
    }

    // 5. Pengaturan pengiriman kode verifikasi WhatsApp (OTP) saat Daftar & Masuk pelanggan
    public function updateVerification(Request $request)
    {
        $data = $request->validate([
            'verification_enabled'          => ['nullable', 'boolean'],
            'verification_hub_url'          => ['required', 'url', 'max:255'],
            'verification_purpose_register' => ['required', 'string', 'min:3', 'max:120'],
            'verification_purpose_login'    => ['required', 'string', 'min:3', 'max:120'],
            'verification_otp_minutes'      => ['required', 'integer', 'min:1', 'max:60'],
            'verification_resend_seconds'   => ['required', 'integer', 'min:5', 'max:600'],
            'verification_max_per_hour'     => ['required', 'integer', 'min:1', 'max:500'],
            'verification_max_attempts'     => ['required', 'integer', 'min:1', 'max:20'],
            'verification_timeout'          => ['required', 'integer', 'min:3', 'max:60'],
        ], [], [
            'verification_hub_url'          => 'URL endpoint pengirim kode',
            'verification_purpose_register' => 'keperluan saat daftar',
            'verification_purpose_login'    => 'keperluan saat masuk',
            'verification_otp_minutes'      => 'masa berlaku kode (menit)',
            'verification_resend_seconds'   => 'jeda kirim ulang (detik)',
            'verification_max_per_hour'     => 'batas maksimal kirim per jam',
            'verification_max_attempts'     => 'batas maksimal salah kode',
            'verification_timeout'          => 'batas waktu tunggu koneksi (detik)',
        ]);

        $this->settings->set([
            'verification_enabled'          => $request->boolean('verification_enabled'),
            'verification_hub_url'          => trim($data['verification_hub_url']),
            'verification_purpose_register' => trim($data['verification_purpose_register']),
            'verification_purpose_login'    => trim($data['verification_purpose_login']),
            'verification_otp_minutes'      => (int) $data['verification_otp_minutes'],
            'verification_resend_seconds'   => (int) $data['verification_resend_seconds'],
            'verification_max_per_hour'     => (int) $data['verification_max_per_hour'],
            'verification_max_attempts'     => (int) $data['verification_max_attempts'],
            'verification_timeout'          => (int) $data['verification_timeout'],
        ]);

        return redirect()->to(route('kasir.settings') . '#verifikasi')->with('status', 'Pengaturan pengiriman kode verifikasi berhasil disimpan.');
    }

    // 5b. Tes kirim kode verifikasi ke nomor WhatsApp dari halaman pengaturan owner
    public function testVerification(Request $request, VerificationHub $hub)
    {
        $data = $request->validate([
            'test_phone' => ['required', 'string', 'max:20'],
        ], [], ['test_phone' => 'nomor WhatsApp uji coba']);

        $phone = Phone::normalize($data['test_phone']);
        if (! $phone) {
            return redirect()->to(route('kasir.settings') . '#verifikasi')->withInput()->withErrors([
                'test_phone' => 'Nomor WhatsApp uji coba tidak valid. Contoh: 081234567890',
            ]);
        }

        $localPhone = Phone::toLocal($phone);
        $code       = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $purpose    = 'Tes Pengiriman Kode Verifikasi (' . $this->settings->businessName() . ')';

        $sent = $hub->send($localPhone, $code, $purpose);
        if (! $sent['ok']) {
            return redirect()->to(route('kasir.settings') . '#verifikasi')->withInput()->withErrors([
                'test_phone' => 'Gagal mengirim kode tes: ' . ($sent['error'] ?? 'Terjadi kesalahan.'),
            ]);
        }

        return redirect()->to(route('kasir.settings') . '#verifikasi')->with(
            'status',
            "Kode verifikasi uji coba ({$code}) berhasil dikirim ke WhatsApp {$localPhone}" . ($sent['request_id'] ? " (ID: {$sent['request_id']})" : '') . '.'
        );
    }

    // 6. Tema web
    public function updateTheme(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(array_keys(Themes::ALL))]], [], ['theme' => 'tema']);
        $this->settings->set(['theme' => $data['theme']]);

        return redirect()->to(route('kasir.settings') . '#tema')->with('status', 'Tema "' . Themes::ALL[$data['theme']]['name'] . '" dipakai.');
    }
}
