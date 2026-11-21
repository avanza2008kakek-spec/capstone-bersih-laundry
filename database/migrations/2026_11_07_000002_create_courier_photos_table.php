<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

/**
 * Foto bukti jemput/antar kurir, disimpan LANGSUNG di database (bukan file), sudah diperkecil (± 50–300 KB).
 * Dipisah dari tabel courier_tasks supaya daftar tugas tetap ringan (isi foto hanya dibaca saat foto dibuka).
 * Di hosting boleh dibuat lewat SQL (lihat database/sql/courier_tasks.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courier_photos')) {
            return;
        }
        Schema::create('courier_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('courier_task_id')->index();          // Tugas kurir terkait
            $table->string('kind', 20)->default('customer');                 // customer (pelanggan) / handover (serah terima kasir & kurir)
            $table->string('mime', 30)->default('image/jpeg');               // Jenis gambar
            $table->unsignedInteger('size');                                 // Ukuran foto (byte) untuk laporan pemakaian database
            $table->binary('data');                                          // Isi foto
            $table->timestamps();
            $table->unique(['courier_task_id', 'kind'], 'courier_photos_task_kind_unique');
        });
        // MySQL/MariaDB: BLOB biasa maks. 64 KB → pakai MEDIUMBLOB (maks. 16 MB)
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE courier_photos MODIFY data MEDIUMBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_photos');
    }
};
