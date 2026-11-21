<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

return new class extends Migration
{
    // Kolom role & phone di tabel users. Di database hosting kolom ini SUDAH ADA,
    // jadi migrasi ini hanya menambahkannya jika belum ada (cth: database baru / test).
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('kasir')->after('password'); // owner / kasir / operator
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('role');          // No. HP staf
            }
        });
    }

    public function down(): void
    {
        // Sengaja kosong: kolom ini dipakai data staf yang sudah ada, jangan dihapus otomatis
    }
};
