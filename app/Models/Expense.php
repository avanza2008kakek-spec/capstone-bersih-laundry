<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import relasi Many-to-One

/** Pengeluaran operasional (kas keluar), cth: beli deterjen, listrik, gaji. */
class Expense extends Model
{
    protected $fillable = [
        'expense_category_id',                                  // Kategori biaya
        'user_id',                                              // Staf yang mencatat
        'expense_date',                                         // Tanggal pengeluaran
        'amount',                                               // Nominal
        'description',                                          // Keterangan (wajib di database)
        'proof_image',                                          // Nama file bukti/struk (opsional)
    ];

    protected $casts = [
        'expense_date' => 'date',                               // Konversi otomatis ke objek tanggal
        'amount'       => 'decimal:2',                          // Format angka desimal uang
    ];

    public function category(): BelongsTo
    {
        // Nama kolom di database "expense_category_id" (bukan "category_id"), jadi disebut langsung
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);                   // Staf yang mencatat pengeluaran
    }
}
