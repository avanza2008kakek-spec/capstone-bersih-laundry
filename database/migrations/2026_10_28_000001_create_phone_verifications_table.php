<?php

use Illuminate\Database\Migrations\Migration;                  // Base class migrasi
use Illuminate\Database\Schema\Blueprint;                       // Pembuat struktur kolom tabel
use Illuminate\Support\Facades\Schema;                          // Facade pengelola skema database

return new class extends Migration
{
    public function up(): void
    {
        // Kode verifikasi (OTP) pendaftaran pelanggan; kodenya dikirim ke WhatsApp lewat POSAJA Verification Hub
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();                                       // ID verifikasi
            $table->string('phone', 20)->index();               // Nomor WA pendaftar format 628xxx
            $table->string('name', 100);                        // Nama yang diisi saat mendaftar
            $table->string('code_hash');                        // Kode OTP dalam bentuk hash (kode asli tidak disimpan)
            $table->unsignedTinyInteger('attempts')->default(0); // Jumlah percobaan memasukkan kode yang salah
            $table->string('hub_request_id', 64)->nullable();   // ID permintaan dari Verification Hub
            $table->dateTime('expires_at');                     // Batas waktu kode berlaku (DATETIME: tidak ikut berubah saat baris di-update)
            $table->timestamp('verified_at')->nullable();       // Terisi saat kode berhasil dicocokkan
            $table->timestamps();                               // created_at & updated_at
        });

        // Tandai pelanggan yang nomor WA-nya sudah terverifikasi (tabel customers sudah ada sebelumnya)
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'phone_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('phone_verified_at')->nullable(); // Waktu nomor WA pelanggan terverifikasi
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'phone_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('phone_verified_at');        // Hapus kolom penanda verifikasi
            });
        }
        Schema::dropIfExists('phone_verifications');            // Hapus tabel OTP
    }
};
