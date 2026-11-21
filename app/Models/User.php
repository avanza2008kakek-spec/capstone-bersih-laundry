<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun staf Area Kasir. Kolom "role" menentukan menu yang boleh dibuka:
 * owner = semua menu, kasir = transaksi & pelanggan, operator = antrean cucian, kurir = tugas jemput & antar.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const OWNER    = 'owner';                            // Pemilik: semua menu, kelola staf & keuangan
    public const KASIR    = 'kasir';                            // Kasir: transaksi, pembayaran, pelanggan
    public const OPERATOR = 'operator';                         // Operator: mengerjakan cucian (antrean & status)
    public const KURIR    = 'kurir';                            // Kurir: menjemput & mengantar cucian (tugas dari kasir)

    /** Daftar role beserta nama tampilannya (dipakai di form Kelola Staf). */
    public const ROLES = [
        self::OWNER    => 'Owner (pemilik)',
        self::KASIR    => 'Kasir',
        self::OPERATOR => 'Operator',
        self::KURIR    => 'Kurir (antar-jemput)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',                                                 // owner / kasir / operator
        'phone',                                                // No. HP staf (opsional)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Role akun; akun lama tanpa role dianggap kasir (paling aman, tanpa akses keuangan & staf). */
    public function roleKey(): string
    {
        return array_key_exists($this->role, self::ROLES) ? $this->role : self::KASIR;
    }

    /** Nama role untuk ditampilkan, cth: "Owner (pemilik)". */
    public function roleLabel(): string
    {
        return self::ROLES[$this->roleKey()];
    }

    /** Apakah akun ini punya salah satu role yang disebut, cth: hasRole('owner', 'kasir'). */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleKey(), $roles, true);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(self::OWNER);
    }
}
