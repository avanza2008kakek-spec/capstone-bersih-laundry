<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reservasi jemput laundry yang dikirim pelanggan dari website. */
class Reservation extends Model
{
    // Alur status: Baru → Dikonfirmasi → Sudah dijemput; atau Dibatalkan
    public const STATUSES = [
        'new'       => 'Baru',
        'confirmed' => 'Dikonfirmasi',
        'picked_up' => 'Sudah dijemput',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = ['customer_id', 'service', 'estimated_weight', 'estimated_price', 'pickup_date', 'address', 'maps_url',
                           'pickup_fee', 'notes', 'status', 'handled_by', 'handled_at'];

    protected $casts = [
        'pickup_date' => 'date',
        'handled_at'  => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Tugas jemput kurir untuk reservasi ini (yang terbaru di atas). */
    public function courierTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourierTask::class)->latest('id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');            // Staf yang memproses
    }

    /**
     * Jumlah reservasi baru (belum diproses) untuk lencana notifikasi di menu kasir.
     * Mengembalikan 0 jika tabel reservations belum dibuat di server, supaya Staff tetap bisa dibuka.
     */
    public static function newCount(): int
    {
        try {
            return static::where('status', 'new')->count();
        } catch (\Illuminate\Database\QueryException $e) {
            return 0;
        }
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
