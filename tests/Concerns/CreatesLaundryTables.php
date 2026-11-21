<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel laundry di hosting dibuat lewat SQL (tanpa migrasi Laravel).
 * Trait ini membuat tabel yang sama persis (mengikuti struktur di server) untuk keperluan test.
 */
trait CreatesLaundryTables
{
    protected function createLaundryTables(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('phone', 30); $t->text('address')->nullable();
            $t->timestamps(); $t->timestamp('phone_verified_at')->nullable(); $t->string('maps_url', 500)->nullable();
        });
        Schema::create('service_categories', function (Blueprint $t) {
            $t->id(); $t->string('name', 100); $t->string('description')->nullable(); $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('category_id'); $t->string('name'); $t->string('unit')->default('kg');
            $t->decimal('price', 12, 2); $t->unsignedInteger('duration_hours')->default(48); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->string('invoice_number', 50); $t->unsignedBigInteger('customer_id'); $t->unsignedBigInteger('user_id');
            $t->dateTime('order_date'); $t->dateTime('estimate_date'); $t->dateTime('completion_date')->nullable();
            $t->decimal('total_amount', 12, 2); $t->decimal('discount', 12, 2)->default(0); $t->decimal('final_amount', 12, 2);
            $t->decimal('paid_amount', 12, 2)->default(0); $t->string('payment_status')->default('unpaid');
            $t->string('order_status')->default('received'); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('service_id'); $t->decimal('quantity', 8, 2);
            $t->decimal('unit_price', 12, 2); $t->decimal('subtotal', 12, 2); $t->string('notes')->nullable(); $t->timestamps();
        });
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('user_id'); $t->string('status', 50);
            $t->string('notes')->nullable(); $t->timestamp('created_at')->nullable();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('user_id'); $t->dateTime('payment_date');
            $t->decimal('amount', 12, 2); $t->string('payment_method')->default('cash'); $t->string('payment_type')->default('full_payment');
            $t->string('proof_image')->nullable(); $t->string('notes')->nullable(); $t->timestamps();
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id(); $t->string('name', 100); $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('expense_category_id'); $t->unsignedBigInteger('user_id'); $t->date('expense_date');
            $t->decimal('amount', 12, 2); $t->text('description'); $t->string('proof_image')->nullable(); $t->timestamps();
        });
    }
}
