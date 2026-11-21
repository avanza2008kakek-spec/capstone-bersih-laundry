<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import tipe relasi Many-to-One
use Illuminate\Database\Eloquent\Relations\HasMany;             // Import tipe relasi One-to-Many

class Service extends Model
{
    protected $fillable = [
        'category_id',                                          // Foreign key ke tabel kategori
        'name',                                                 // Nama layanan (cth: Cuci Kering Setrika)
        'unit',                                                 // Satuan tarif (kg, pcs, atau meter)
        'price',                                                // Tarif harga per unit
        'duration_hours',                                       // Lama pengerjaan dalam satuan jam
        'is_active'                                             // Status keaktifan layanan (1: aktif, 0: non-aktif)
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id'); // Relasi balik: Setiap layanan milik satu kategori
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);                // Relasi: Layanan ini bisa tercatat di banyak rincian pesanan
    }
}