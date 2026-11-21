<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import relasi Many-to-One

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',                                             // ID pesanan induk
        'service_id',                                           // ID layanan yang dipilih
        'quantity',                                             // Kuantitas atau bobot timbangan (kg/pcs)
        'unit_price',                                           // Harga satuan saat order dicatat
        'subtotal',                                             // Nilai hasil perkalian quantity * unit_price
        'notes'                                                 // Catatan per item (warna, luntur, dll)
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);                  // Relasi balik ke nota pesanan induk
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);                // Relasi ke data master layanan
    }
}