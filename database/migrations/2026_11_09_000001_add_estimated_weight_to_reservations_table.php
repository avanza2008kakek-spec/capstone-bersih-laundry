<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                if (! Schema::hasColumn('reservations', 'estimated_weight')) {
                    $table->string('estimated_weight', 50)->nullable()->after('service');
                }
                if (! Schema::hasColumn('reservations', 'estimated_price')) {
                    $table->string('estimated_price', 50)->nullable()->after('estimated_weight');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $table) {
                if (Schema::hasColumn('reservations', 'estimated_weight')) {
                    $table->dropColumn('estimated_weight');
                }
                if (Schema::hasColumn('reservations', 'estimated_price')) {
                    $table->dropColumn('estimated_price');
                }
            });
        }
    }
};

