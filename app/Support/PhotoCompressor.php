<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Perkecil foto bukti kurir supaya hemat database: sisi terpanjang maks. 1024 px, JPEG, target ≤ 300 KB.
 * Foto sudah diperkecil di HP kurir (JavaScript); di sini diperkecil lagi jika server punya ekstensi GD.
 * Tanpa GD, foto diterima apa adanya selama ukurannya ≤ 300 KB.
 */
class PhotoCompressor
{
    public const MAX_SIDE  = 1024;                              // Piksel sisi terpanjang
    public const MAX_BYTES = 300 * 1024;                        // Batas ukuran foto yang disimpan

    /** @return array{data: string, mime: string, size: int}|null  null = foto terlalu besar dan tidak bisa diperkecil */
    public static function compress(UploadedFile $file): ?array
    {
        $raw = (string) file_get_contents($file->getRealPath());

        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($raw))) {
            $w = imagesx($img);
            $h = imagesy($img);
            $scale = min(1, self::MAX_SIDE / max($w, $h));
            if ($scale < 1) {
                $resized = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
                imagecopyresampled($resized, $img, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            // Turunkan kualitas JPEG bertahap sampai ukurannya masuk batas
            foreach ([72, 62, 52, 42] as $quality) {
                ob_start();
                imagejpeg($img, null, $quality);
                $jpeg = (string) ob_get_clean();
                if (strlen($jpeg) <= self::MAX_BYTES) break;
            }
            imagedestroy($img);
            if (strlen($jpeg) <= self::MAX_BYTES) {
                return ['data' => $jpeg, 'mime' => 'image/jpeg', 'size' => strlen($jpeg)];
            }
        }

        // Tanpa GD: simpan apa adanya jika sudah kecil (sudah diperkecil di HP)
        if (strlen($raw) <= self::MAX_BYTES) {
            return ['data' => $raw, 'mime' => $file->getMimeType() ?: 'image/jpeg', 'size' => strlen($raw)];
        }
        return null;
    }
}
