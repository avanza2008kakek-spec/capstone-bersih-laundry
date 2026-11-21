<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import relasi Many-to-One

class Payment extends Model
{
    protected $fillable = [
        'order_id',                                             // Foreign key pesanan yang dibayar
        'user_id',                                              // Kasir penerima uang
        'payment_date',                                         // Tanggal dan jam pembayaran dilakukan
        'amount',                                               // Jumlah nominal uang yang disetorkan
        'payment_method',                                       // Metode: cash, qris, transfer
        'payment_type',                                         // Jenis: down_payment (DP), settlement (pelunasan), full_payment
        'proof_image',                                          // Nama file gambar bukti transfer/struk
        'notes'                                                 // Catatan tambahan pembayaran
    ];

    protected $casts = [
        'payment_date' => 'datetime',                           // Konversi ke instance objek Carbon datetime
        'amount'       => 'decimal:2',                          // Format nilai nominal mata uang
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);                  // Relasi balik: Pembayaran terhubung ke 1 pesanan
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);                   // Relasi balik: Kasir yang menerima pembayaran
    }
}