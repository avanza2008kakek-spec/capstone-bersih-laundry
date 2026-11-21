<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent

class PhoneVerification extends Model
{
    public const MAX_ATTEMPTS = 5;                              // Maksimal salah memasukkan kode sebelum kode hangus

    protected $fillable = [
        'phone',                                                // Nomor WA pendaftar (628xxx)
        'name',                                                 // Nama pendaftar
        'code_hash',                                            // Hash kode OTP
        'attempts',                                             // Jumlah percobaan salah
        'hub_request_id',                                       // ID permintaan dari POSAJA Verification Hub (untuk penelusuran)
        'expires_at',                                           // Batas waktu kode
        'verified_at',                                          // Waktu berhasil diverifikasi
    ];

    protected $hidden = ['code_hash'];                          // Jangan pernah ikut tampil saat model diubah ke JSON

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isUsable(?int $maxAttempts = null): bool
    {
        $limit = $maxAttempts ?? (int) (app(\App\Support\Settings::class)->verificationConfig()['max_attempts'] ?? self::MAX_ATTEMPTS);

        // Kode masih bisa dipakai jika: belum diverifikasi, belum kedaluwarsa, dan belum terlalu sering salah
        return $this->verified_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < $limit;
    }
}
