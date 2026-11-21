<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

/**
 * Tabel reservasi dari website (untuk notifikasi & daftar Reservasi di Area Kasir).
 * Di hosting boleh dibuat lewat SQL (lihat database/sql/reservations.sql); hasTable mencegah dibuat dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            return;
        }
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();              // Pelanggan yang reservasi
            $table->string('service', 100);                                  // Layanan yang dipilih
            $table->string('estimated_weight', 50)->nullable();              // Perkiraan berat/jumlah dari pelanggan
            $table->string('estimated_price', 50)->nullable();               // Perkiraan harga cucian
            $table->date('pickup_date');                                     // Tanggal jemput yang diminta
            $table->text('address');                                         // Alamat jemput saat reservasi dibuat
            $table->string('maps_url', 500)->nullable();                     // Lokasi Google Maps
            $table->string('pickup_fee', 100)->nullable();                   // Perkiraan ongkos jemput (teks)
            $table->string('notes', 200)->nullable();                        // Catatan pelanggan
            $table->string('status', 20)->default('new')->index();           // new / confirmed / picked_up / cancelled
            $table->unsignedBigInteger('handled_by')->nullable();            // Staf yang terakhir memproses
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
