<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\HasMany;             // Import tipe relasi One-to-Many

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'description'];              // Kolom nama dan deskripsi kategori layanan

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');   // Relasi: Satu kategori menaungi banyak paket layanan
    }
}