<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\HasMany;             // Import relasi One-to-Many

class ExpenseCategory extends Model
{
    protected $fillable = ['name'];                             // Nama kategori biaya (misal: Bahan Kimia, Listrik, Gaji)

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id'); // Relasi: Satu kategori pengeluaran memiliki banyak catatan transaksi
    }
}