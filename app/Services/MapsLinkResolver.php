<?php

namespace App\Services;

use App\Models\Customer;                                        // Pola link Google Maps yang diterima
use App\Support\PickupFee;                                      // Ambil koordinat dari link
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Buka link pendek Google Maps (maps.app.goo.gl/..., goo.gl/maps/...) untuk mendapatkan titik koordinatnya,
 * supaya ongkos jemput & pratinjau peta tetap bisa dihitung walau pelanggan menempel link dari tombol "Bagikan".
 *
 * Keamanan: hanya mengikuti pengalihan (redirect) ke alamat Google Maps (pola Customer::MAPS_URL_PATTERN),
 * maksimal 5 kali, dengan batas waktu singkat. Hasil disimpan di cache 30 hari.
 */
class MapsLinkResolver
{
    private const MAX_HOPS = 5;

    /**
     * Ubah link Google Maps menjadi link berkoordinat "https://www.google.com/maps?q=lat,lng".
     * Link yang sudah berisi koordinat dikembalikan apa adanya; null jika titiknya tidak bisa diketahui.
     */
    public function resolve(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match(Customer::MAPS_URL_PATTERN, $url)) {
            return null;
        }
        if (PickupFee::coords($url)) {
            return $url;                                        // Sudah berkoordinat → tidak perlu dibuka
        }

        // Hanya hasil yang berhasil disimpan di cache (gagal karena koneksi → dicoba lagi lain kali)
        $key = 'mapslink:' . md5($url);
        $coords = Cache::get($key);
        if (! $coords && ($coords = $this->follow($url))) {
            Cache::put($key, $coords, now()->addDays(30));
        }
        return $coords ? self::canonical($coords) : null;
    }

    /** Link standar berkoordinat (6 angka desimal ≈ ketelitian 10 cm). */
    public static function canonical(array $c): string
    {
        return 'https://www.google.com/maps?q=' . number_format($c[0], 6, '.', '') . ',' . number_format($c[1], 6, '.', '');
    }

    /** Ikuti pengalihan link pendek satu per satu sampai ketemu koordinat. */
    private function follow(string $url): ?array
    {
        try {
            for ($i = 0; $i < self::MAX_HOPS; $i++) {
                $res = Http::withoutRedirecting()->timeout(6)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ' . config('app.name') . ')'])
                    ->get($url);

                $next = $res->header('Location');
                if (! $res->redirect() || ! $next) {
                    // Halaman akhir: kadang koordinat hanya ada di isi halaman (meta/og:image "center=lat,lng")
                    return $res->successful() && preg_match('/center=(-?\d+\.\d+)(?:%2C|,)(-?\d+\.\d+)/', $res->body(), $m)
                        ? [(float) $m[1], (float) $m[2]] : null;
                }
                if (str_starts_with($next, '/')) {
                    $next = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . $next;
                }
                if (! preg_match(Customer::MAPS_URL_PATTERN, $next)) {
                    return null;                                // Bukan ke Google Maps lagi → berhenti (titiknya tidak dipercaya)
                }
                if ($c = PickupFee::coords($next)) {
                    return $c;
                }
                $url = $next;
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal membuka link Google Maps: ' . $e->getMessage());
        }
        return null;
    }
}
