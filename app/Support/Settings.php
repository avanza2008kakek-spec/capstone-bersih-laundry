<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;                        // Akses file di storage/app/private

/**
 * Pengaturan usaha yang bisa diubah dari menu Kasir → Pengaturan Usaha.
 * Disimpan di file storage/app/private/settings.json (tidak perlu tabel database,
 * dan folder storage tidak bisa dibuka dari browser).
 */
class Settings
{
    private const FILE = 'settings.json';

    /** Nilai bawaan jika belum pernah diubah. */
    public const DEFAULTS = [
        'business_name' => 'Bersih Laundry',                    // Nama laundry yang tampil di seluruh website & pesan WA
        'theme'         => 'pastel',                            // Tema warna web (lihat App\Support\Themes)
        'shop_address'  => '',                                  // Alamat tempat laundry
        'shop_maps_url' => '',                                  // Link Google Maps lokasi laundry (berisi koordinat)
        'pickup_rates'  => [],                                  // Tarif jemput: [['max_km' => 2, 'price' => 0], ['max_km' => 5, 'price' => 5000], ...]
        'bank_accounts'                 => [],                  // Rekening transfer: [['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Bersih Laundry'], ...]
        'qris_image'                    => '',                  // Gambar QRIS dalam format data URI (data:image/jpeg;base64,...)
        'verification_enabled'          => true,                // Aktifkan pengiriman kode OTP WA saat daftar/masuk pelanggan
        'verification_hub_url'          => '',                  // Kosong = ikuti config('services.verification_hub.url')
        'verification_purpose_register' => 'Verifikasi Pendaftaran Pelanggan Laundry',
        'verification_purpose_login'    => 'Login Pelanggan Laundry',
        'verification_otp_minutes'      => 10,                  // Masa berlaku kode OTP (menit)
        'verification_resend_seconds'   => 60,                  // Jeda tunggu kirim ulang kode (detik)
        'verification_max_per_hour'     => 5,                   // Maksimal permintaan kode per nomor per jam
        'verification_max_attempts'     => 5,                   // Maksimal percobaan salah masukkan kode
        'verification_timeout'          => 15,                  // Timeout koneksi ke Verification Hub (detik)
    ];

    private ?array $values = null;                              // Isi pengaturan yang sudah dibaca (dibaca sekali per request)

    /** Semua pengaturan: nilai tersimpan ditimpakan ke nilai bawaan. */
    public function all(): array
    {
        if ($this->values === null) {
            $disk = Storage::disk('local');
            $saved = $disk->exists(self::FILE) ? json_decode((string) $disk->get(self::FILE), true) : [];
            $this->values = array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);
        }
        return $this->values;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** Simpan beberapa pengaturan sekaligus, cth: set(['business_name' => 'Laundry Wangi']) */
    public function set(array $values): void
    {
        $this->values = array_merge($this->all(), $values);
        Storage::disk('local')->put(self::FILE, json_encode($this->values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Nama laundry saat ini (cth: "Bersih Laundry"). */
    public function businessName(): string
    {
        return (string) $this->get('business_name');
    }

    /** Kunci tema yang dipakai (selalu salah satu tema yang dikenal). */
    public function themeKey(): string
    {
        $key = (string) $this->get('theme');
        return array_key_exists($key, Themes::ALL) ? $key : 'pastel';
    }

    /** Tarif jemput urut dari jarak terdekat. */
    public function pickupRates(): array
    {
        $rates = is_array($this->get('pickup_rates')) ? $this->get('pickup_rates') : [];
        usort($rates, fn ($a, $b) => $a['max_km'] <=> $b['max_km']);
        return $rates;
    }

    /** Daftar rekening bank yang bisa ditransfer untuk pembayaran nota. */
    public function bankAccounts(): array
    {
        $accounts = $this->get('bank_accounts');
        return is_array($accounts) ? array_values($accounts) : [];
    }

    /** Gambar QRIS (data URI) jika sudah diatur oleh owner, atau null jika belum ada. */
    public function qrisImage(): ?string
    {
        $img = trim((string) $this->get('qris_image'));
        return $img !== '' ? $img : null;
    }

    /** Pengaturan lengkap pengiriman kode verifikasi WhatsApp (OTP). */
    public function verificationConfig(): array
    {
        $defaultUrl     = (string) config('services.verification_hub.url', 'https://posaja.kantorposbekasi.com/api/kirim-verifikasi.php');
        $defaultPurpose = (string) config('services.verification_hub.purpose', self::DEFAULTS['verification_purpose_register']);
        $defaultLogin   = (string) config('services.verification_hub.purpose_login', self::DEFAULTS['verification_purpose_login']);
        $defaultMinutes = (int) config('services.verification_hub.otp_minutes', self::DEFAULTS['verification_otp_minutes']);
        $defaultTimeout = (int) config('services.verification_hub.timeout', self::DEFAULTS['verification_timeout']);

        $savedUrl     = trim((string) $this->get('verification_hub_url'));
        $savedReg     = trim((string) $this->get('verification_purpose_register'));
        $savedLogin   = trim((string) $this->get('verification_purpose_login'));

        return [
            'enabled'          => (bool) ($this->get('verification_enabled') ?? true),
            'url'              => $savedUrl !== '' ? $savedUrl : $defaultUrl,
            'purpose_register' => $savedReg !== '' ? $savedReg : $defaultPurpose,
            'purpose_login'    => $savedLogin !== '' ? $savedLogin : $defaultLogin,
            'otp_minutes'      => max(1, (int) ($this->get('verification_otp_minutes') ?: $defaultMinutes)),
            'resend_seconds'   => max(5, (int) ($this->get('verification_resend_seconds') ?: self::DEFAULTS['verification_resend_seconds'])),
            'max_per_hour'     => max(1, (int) ($this->get('verification_max_per_hour') ?: self::DEFAULTS['verification_max_per_hour'])),
            'max_attempts'     => max(1, (int) ($this->get('verification_max_attempts') ?: self::DEFAULTS['verification_max_attempts'])),
            'timeout'          => max(3, (int) ($this->get('verification_timeout') ?: $defaultTimeout)),
        ];
    }
}
