<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

return new class extends Migration
{
    public function up(): void
    {
        // Link lokasi Google Maps alamat penjemputan pelanggan (diisi saat reservasi)
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'maps_url')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('maps_url', 500)->nullable();    // cth: https://maps.google.com/?q=-6.2383,106.9756
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'maps_url')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('maps_url');
            });
        }
    }
};
