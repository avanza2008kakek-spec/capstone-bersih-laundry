<?php

namespace App\Services;

use App\Support\Settings;                                       // Pengaturan usaha (nama laundry)
use Illuminate\Http\Client\ConnectionException;                // Error saat Hub tidak bisa dihubungi
use Illuminate\Support\Facades\Http;                            // HTTP client bawaan Laravel
use Illuminate\Support\Facades\Log;                             // Catat jawaban Hub jika gagal

/**
 * Pengirim kode verifikasi lewat POSAJA Verification Hub
 * (https://posaja.kantorposbekasi.com/api/kirim-verifikasi.php).
 * Hub yang meneruskan kode ke WhatsApp pelanggan.
 */
class VerificationHub
{
    public function __construct(private Settings $settings)    // Nama laundry diambil dari Pengaturan Usaha
    {
    }

    /**
     * Kirim permintaan verifikasi ke Hub.
     *
     * @param  string  $phoneLocal  Nomor WA format lokal, cth: 081234567890
     * @param  string  $code        Kode OTP 6 digit buatan website Laundry
     * @param  string|null  $purpose  Keperluan yang tampil di pesan WA (default: pendaftaran)
     * @param  string|null  $callbackUrl  Alamat halaman Laundry yang mengisi kode otomatis; Hub memasang link ke alamat ini di pesan WA
     * @return array{ok: bool, request_id: ?string, code: ?string, error: ?string}
     */
    public function send(string $phoneLocal, string $code, ?string $purpose = null, ?string $callbackUrl = null): array
    {
        $cfg = $this->settings->verificationConfig();

        try {
            $response = Http::timeout($cfg['timeout'])          // Jangan menunggu Hub terlalu lama
                ->acceptJson()
                ->asJson()                                      // Kirim sebagai JSON (Content-Type: application/json)
                ->post($cfg['url'], [
                    'app_name'  => $this->settings->businessName(), // Nama laundry (menu Kasir → Pengaturan Usaha)
                    'nomor_wa'  => $phoneLocal,                 // Nomor tujuan
                    'keperluan' => $purpose ?? $cfg['purpose_register'], // Keperluan kode (daftar / masuk)
                    'kode_otp'  => $code,                       // Kode dari Laundry, supaya bisa dicocokkan di sini
                ] + ($callbackUrl ? ['callback_url' => $callbackUrl] : [])); // Tujuan link verifikasi otomatis di pesan WA (dibuat oleh Hub)
        } catch (ConnectionException $e) {
            Log::warning('Verification Hub tidak bisa dihubungi', ['error' => $e->getMessage()]);
            return $this->fail('Layanan pengirim kode sedang tidak bisa dihubungi.');
        }

        $data = $response->json() ?? [];

        // Dianggap berhasil jika Hub menjawab 2xx dan tidak menyatakan gagal
        $failedFlag = (isset($data['success']) && $data['success'] === false)
            || (isset($data['status']) && in_array(strtolower((string) $data['status']), ['error', 'failed', 'gagal'], true));

        if (! $response->successful() || $failedFlag) {
            Log::warning('Verification Hub menolak permintaan', ['http' => $response->status(), 'body' => $response->body()]);
            return $this->fail($data['error'] ?? $data['message'] ?? 'Kode verifikasi gagal dikirim.'); // Hub menaruh alasan gagal di "error"
        }

        return [
            'ok'                 => true,
            'request_id'         => isset($data['request_id']) ? (string) $data['request_id'] : null,
            'code'               => isset($data['kode_otp']) ? (string) $data['kode_otp'] : null, // Kode yang benar-benar dikirim Hub
            'cooldown_seconds'   => isset($data['cooldown_seconds']) ? (int) $data['cooldown_seconds'] : null,
            'otp_expiry_minutes' => isset($data['otp_expiry_minutes']) ? (int) $data['otp_expiry_minutes'] : null,
            'error'              => null,
        ];
    }

    private function fail(string $error): array
    {
        return ['ok' => false, 'request_id' => null, 'code' => null, 'cooldown_seconds' => null, 'otp_expiry_minutes' => null, 'error' => $error];
    }
}
