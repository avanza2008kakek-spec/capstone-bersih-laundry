<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import relasi Many-to-One

class OrderStatusLog extends Model
{
    public $timestamps = false;                                 // Hanya menggunakan kolom created_at bawaan DDL
    protected $fillable = ['order_id', 'user_id', 'status', 'notes', 'created_at'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);                  // Relasi balik ke pesanan yang diperbarui
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);                   // Relasi ke staf/operator yang mengubah status
    }
}