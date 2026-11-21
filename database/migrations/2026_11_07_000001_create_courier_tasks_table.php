<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

/**
 * Tugas kurir: jemput cucian (dari reservasi) & antar cucian (dari nota), beserta foto bukti.
 * Di hosting boleh dibuat lewat SQL (lihat database/sql/courier_tasks.sql); hasTable mencegah dibuat dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courier_tasks')) {
            return;
        }
        Schema::create('courier_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                                      // pickup (jemput) / delivery (antar)
            $table->unsignedBigInteger('reservation_id')->nullable()->index(); // Untuk tugas jemput
            $table->unsignedBigInteger('order_id')->nullable()->index();     // Untuk tugas antar
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('courier_id')->index();               // Akun kurir yang ditugaskan
            $table->unsignedBigInteger('assigned_by');                       // Kasir/owner yang menugaskan
            $table->date('scheduled_date');                                  // Tanggal jemput/antar
            $table->text('address');                                         // Alamat tujuan
            $table->string('maps_url', 500)->nullable();                     // Lokasi Google Maps tujuan
            $table->string('notes', 255)->nullable();                        // Pesan kasir untuk kurir
            $table->string('status', 20)->default('assigned')->index();      // assigned / on_the_way / done / failed / cancelled
            $table->string('courier_notes', 255)->nullable();                // Catatan kurir / alasan gagal
            $table->timestamp('started_at')->nullable();                     // Kurir berangkat
            $table->timestamp('finished_at')->nullable();                    // Selesai / gagal
            $table->timestamp('handover_at')->nullable();                    // Waktu serah terima antara kurir & kasir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_tasks');
    }
};
