<?php

use App\Models\CourierPhoto;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CourierPhoto::ensureSchema();
    }

    public function down(): void
    {
    }
};

