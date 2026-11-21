<?php

namespace App\Support;

class Phone
{
    /**
     * Ubah nomor HP Indonesia ke format internasional tanpa "+" (628xxxxxxxxx).
     * Contoh: "0812-3456-7890", "+62 812 3456 7890", "81234567890" → "6281234567890".
     * Mengembalikan null jika bukan nomor HP Indonesia yang wajar.
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);    // Buang semua selain angka (spasi, -, +, titik)

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);                // 08xx → 628xx
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;                           // 8xx → 628xx
        }

        // Nomor HP Indonesia: 62 + 8 + 8 s.d. 11 digit (total 10–13 digit setelah 62)
        return preg_match('/^628\d{8,11}$/', $digits) ? $digits : null;
    }

    /**
     * Semua bentuk penulisan yang mungkin tersimpan di data pelanggan lama,
     * supaya pelanggan yang sudah pernah dicatat kasir tetap ditemukan.
     */
    public static function variants(string $normalized): array
    {
        $local = '0' . substr($normalized, 2);                 // 628xx → 08xx
        return [$normalized, '+' . $normalized, $local];
    }

    /** Format lokal untuk ditampilkan/disimpan: 6281234567890 → 081234567890 */
    public static function toLocal(string $normalized): string
    {
        return '0' . substr($normalized, 2);
    }
}
