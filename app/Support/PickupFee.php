<?php

namespace App\Support;

/**
 * Hitung ongkos jemput dari jarak lokasi laundry ke lokasi pelanggan.
 * Jarak = garis lurus antar-titik (rumus haversine), karena jarak jalan raya butuh API berbayar.
 * Tarif diatur owner di Pengaturan Usaha → Ongkos jemput.
 */
class PickupFee
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * Ambil koordinat dari link Google Maps. Null jika link tidak berisi koordinat (cth: link pendek maps.app.goo.gl).
     * Bentuk yang dikenali: ?q=lat,lng · ?query= · ?ll= · /search/lat,+lng · !3dlat!4dlng (titik tempat) · @lat,lng (tengah peta)
     */
    public static function coords(?string $url): ?array
    {
        if (! $url) return null;
        $url = urldecode($url);                                 // %2C → koma, %2B → +
        $num = '(-?\d+(?:\.\d+)?)';
        if (preg_match('/[?&](?:q|query|ll)=' . $num . ',\s*\+?' . $num . '/', $url, $m)
            || preg_match('#/maps/(?:search|place)/' . $num . ',\s*\+?' . $num . '#', $url, $m)
            || preg_match('/!3d' . $num . '!4d' . $num . '/', $url, $m)
            || preg_match('/@' . $num . ',' . $num . '/', $url, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            return (abs($lat) <= 90 && abs($lng) <= 180) ? [$lat, $lng] : null;
        }
        return null;
    }

    /** Jarak garis lurus 2 titik dalam kilometer (rumus haversine, jari-jari bumi 6.371 km). */
    public static function distanceKm(array $a, array $b): float
    {
        [$lat1, $lng1] = array_map('deg2rad', $a);
        [$lat2, $lng2] = array_map('deg2rad', $b);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;
        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    /**
     * Ongkos jemput untuk lokasi pelanggan.
     * @return array{status: string, distance_km: ?float, fee: ?int}
     *   status: ok | out_of_range (lebih jauh dari tarif terjauh) | unknown (lokasi laundry/pelanggan/tarif belum ada)
     */
    public function forCustomerUrl(?string $customerMapsUrl): array
    {
        $shop = self::coords($this->settings->get('shop_maps_url'));
        $cust = self::coords($customerMapsUrl);
        $rates = $this->settings->pickupRates();

        if (! $shop || ! $cust || ! $rates) {
            return ['status' => 'unknown', 'distance_km' => null, 'fee' => null];
        }

        $km = round(self::distanceKm($shop, $cust), 1);
        foreach ($rates as $rate) {
            if ($km <= (float) $rate['max_km']) {
                return ['status' => 'ok', 'distance_km' => $km, 'fee' => (int) $rate['price']];
            }
        }
        return ['status' => 'out_of_range', 'distance_km' => $km, 'fee' => null];
    }

    /** Lokasi laundry & tarif sudah diatur owner (ongkos bisa dihitung otomatis). */
    public function isConfigured(): bool
    {
        return self::coords($this->settings->get('shop_maps_url')) && $this->settings->pickupRates();
    }

    /** Teks ringkasan di form reservasi, cth: "Rp5.000 (±3,3 km)", "Gratis (±1,1 km)". Null jika belum bisa dihitung. */
    public static function summary(array $r): ?string
    {
        if ($r['status'] === 'unknown') return null;
        $jarak = '±' . number_format($r['distance_km'], 1, ',', '.') . ' km';
        $harga = $r['status'] === 'out_of_range' ? 'di luar area jemput'
            : ($r['fee'] > 0 ? 'Rp' . number_format($r['fee'], 0, ',', '.') : 'Gratis');
        return "$harga ($jarak)";
    }

    /** Kalimat ringkas untuk pesan WA / tampilan, cth: "±3,2 km · Rp5.000". */
    public static function describe(array $r): string
    {
        return match ($r['status']) {
            'ok'           => '±' . number_format($r['distance_km'], 1, ',', '.') . ' km · ' . ($r['fee'] > 0 ? 'Rp' . number_format($r['fee'], 0, ',', '.') : 'Gratis'),
            'out_of_range' => '±' . number_format($r['distance_km'], 1, ',', '.') . ' km · di luar area jemput (konfirmasi admin)',
            default        => 'dihitung admin',
        };
    }
}
