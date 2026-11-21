<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;                // Error saat layanan peta tidak bisa dihubungi
use Illuminate\Support\Facades\Cache;                           // Simpan hasil supaya tidak bertanya berulang-ulang
use Illuminate\Support\Facades\Http;                            // HTTP client bawaan Laravel
use Illuminate\Support\Facades\Log;

/**
 * Ubah titik GPS (lintang, bujur) menjadi alamat teks memakai OpenStreetMap Nominatim
 * (gratis, tanpa API key). Bagian yang tidak ditemukan diberi penanda "(isi)"
 * supaya pelanggan wajib melengkapinya sendiri.
 */
class ReverseGeocoder
{
    public const PLACEHOLDER = '(isi)';                         // Penanda bagian alamat yang harus dilengkapi pelanggan
    private const URL = 'https://nominatim.openstreetmap.org/reverse';
    private const SEARCH_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Cari tempat/jalan berdasarkan teks (untuk kotak "Cari" di peta pilih titik).
     * @return array{ok: bool, results: array<array{label: string, lat: float, lng: float}>, error: ?string}
     */
    public function search(string $query): array
    {
        $query = trim($query);
        $key = 'geosearch:' . md5(mb_strtolower($query));

        $results = Cache::get($key);
        if ($results === null) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders($this->headers())
                    ->get(self::SEARCH_URL, [
                        'format' => 'jsonv2', 'q' => $query,
                        'countrycodes' => 'id',                 // Hanya tempat di Indonesia
                        'limit' => 5,
                    ]);
            } catch (ConnectionException $e) {
                Log::warning('Nominatim (cari) tidak bisa dihubungi', ['error' => $e->getMessage()]);
                return ['ok' => false, 'results' => [], 'error' => 'Pencarian sedang tidak bisa dipakai. Geser peta secara manual.'];
            }
            if (! $response->successful() || ! is_array($response->json())) {
                return ['ok' => false, 'results' => [], 'error' => 'Pencarian sedang tidak bisa dipakai. Geser peta secara manual.'];
            }

            // Ambil yang perlu saja: nama tempat lengkap + koordinatnya
            $results = collect($response->json())->map(fn ($r) => [
                'label' => (string) ($r['display_name'] ?? ''),
                'lat'   => (float) ($r['lat'] ?? 0),
                'lng'   => (float) ($r['lon'] ?? 0),
            ])->filter(fn ($r) => $r['label'] !== '')->values()->all();
            Cache::put($key, $results, now()->addDay());
        }

        return ['ok' => true, 'results' => $results, 'error' => null];
    }

    // Aturan pemakaian Nominatim: wajib menyebut nama aplikasi & alamat situs; nama tempat dalam bahasa Indonesia
    private function headers(): array
    {
        return [
            'User-Agent'      => config('app.name', 'Laundry') . ' (' . config('app.url') . ')',
            'Accept-Language' => 'id',
        ];
    }

    /**
     * @return array{ok: bool, address: ?string, missing: array<string>, error: ?string}
     *   address = alamat siap tampil, missing = daftar bagian yang belum ditemukan (cth: ['nomor rumah', 'RT/RW'])
     */
    public function lookup(float $lat, float $lng): array
    {
        // Koordinat dibulatkan ±11 meter untuk kunci cache (titik berdekatan memakai hasil yang sama)
        $key = sprintf('revgeo:%.4f,%.4f', $lat, $lng);

        $data = Cache::get($key);
        if ($data === null) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders($this->headers())
                    ->get(self::URL, [
                        'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng,
                        'zoom' => 18, 'addressdetails' => 1,    // zoom 18 = tingkat bangunan/jalan
                    ]);
            } catch (ConnectionException $e) {
                Log::warning('Nominatim tidak bisa dihubungi', ['error' => $e->getMessage()]);
                return $this->fail();
            }

            if (! $response->successful() || ! is_array($response->json('address'))) {
                return $this->fail();
            }
            $data = $response->json('address');
            Cache::put($key, $data, now()->addDay());           // Simpan 1 hari
        }

        return $this->compose($data);
    }

    /** Susun alamat gaya Indonesia: Jalan No. x, RT/RW, Kel., Kec., Kota, Kode pos. */
    public function compose(array $a): array
    {
        $missing = [];
        $p = self::PLACEHOLDER;

        // 1. Nama jalan + nomor rumah
        $road = $a['road'] ?? $a['pedestrian'] ?? $a['residential'] ?? null;
        if (! $road) {
            $missing[] = 'nama jalan';
            $road = "Jl. {$p}";
        }
        if (! empty($a['house_number'])) {
            $street = "{$road} No. {$a['house_number']}";
        } else {
            $missing[] = 'nomor rumah';
            $street = "{$road} No. {$p}";
        }

        // 2. RT/RW: kadang tercatat di "neighbourhood"/"hamlet" (cth: "RW 05"), sering tidak ada sama sekali
        $rtrw = null;
        foreach (['neighbourhood', 'hamlet', 'quarter'] as $field) {
            if (! empty($a[$field]) && preg_match('/\b(RT|RW)\b/i', $a[$field])) {
                $rtrw = $a[$field];
                break;
            }
        }
        if (! $rtrw) {
            $missing[] = 'RT/RW';
            $rtrw = "RT {$p}/RW {$p}";
        }

        // 3. Kelurahan/desa, kecamatan, kota/kabupaten, kode pos (dipakai jika ada)
        $kel  = $a['village'] ?? $a['suburb'] ?? null;
        $kec  = $a['city_district'] ?? $a['district'] ?? $a['subdistrict'] ?? null;
        $kota = $a['city'] ?? $a['town'] ?? $a['county'] ?? $a['municipality'] ?? null;

        $parts = array_filter([
            $street,
            $rtrw,
            $kel ? (preg_match('/^(Kel|Desa)/i', $kel) ? $kel : "Kel. {$kel}") : null,
            $kec ? (preg_match('/^Kec/i', $kec) ? $kec : "Kec. {$kec}") : null,
            $kota,
            $a['postcode'] ?? null,
        ]);

        return ['ok' => true, 'address' => implode(', ', $parts), 'missing' => $missing, 'error' => null];
    }

    private function fail(): array
    {
        return ['ok' => false, 'address' => null, 'missing' => [], 'error' => 'Alamat dari lokasi belum bisa diambil. Silakan ketik alamat secara manual.'];
    }
}
