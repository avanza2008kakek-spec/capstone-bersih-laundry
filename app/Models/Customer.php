<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\HasMany;             // Import tipe relasi One-to-Many

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'maps_url', 'phone_verified_at']; // Kolom yang diizinkan untuk diisi secara massal (maps_url = link lokasi Google Maps)

    protected $casts = [
        'phone_verified_at' => 'datetime',                      // Waktu nomor WA terverifikasi lewat kode OTP
    ];

    // Link Google Maps yang diterima: google.com/maps, maps.google.com, maps.app.goo.gl, goo.gl/maps
    public const MAPS_URL_PATTERN = '#^https://((www\.)?google\.[a-z.]+/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl/maps)(/|\?|$)#i';

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);                    // Relasi: Satu pelanggan bisa memiliki banyak transaksi pesanan
    }

    /** Alamat default = alamat DAN lokasi Google Maps sudah sama-sama terisi. */
    public function hasDefaultAddress(): bool
    {
        return filled($this->address) && filled($this->maps_url);
    }

    /**
     * Aturan cek isian alamat & lokasi (dipakai di Reservasi dan Edit Alamat).
     * @param bool $mapsRequired true = lokasi Google Maps wajib diisi
     */
    public static function addressRules(bool $mapsRequired): array
    {
        return [
            // not_regex: tolak alamat dari GPS yang penanda "(isi)"-nya belum dilengkapi
            'address'  => ['required', 'string', 'min:10', 'max:500', 'not_regex:/\(isi\)/i'],
            'maps_url' => [$mapsRequired ? 'required' : 'nullable', 'string', 'max:500', 'regex:' . self::MAPS_URL_PATTERN],
        ];
    }

    /** Pesan error isian alamat & lokasi dalam bahasa Indonesia. */
    public static function addressMessages(): array
    {
        return [
            'address.required'  => 'Alamat penjemputan wajib diisi.',
            'address.min'       => 'Alamat terlalu pendek. Tulis nama jalan, nomor rumah, dan patokan.',
            'address.not_regex' => 'Lengkapi dulu bagian alamat yang bertanda (isi), misalnya nomor rumah dan RT/RW.',
            'maps_url.required' => 'Lokasi Google Maps wajib diisi. Gunakan tombol "Gunakan lokasi saya" atau tempel link dari Google Maps.',
            'maps_url.regex'    => 'Link lokasi harus dari Google Maps (cth: https://maps.app.goo.gl/...).',
        ];
    }
}