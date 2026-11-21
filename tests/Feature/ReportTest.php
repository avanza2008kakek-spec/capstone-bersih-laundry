<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/**
 * Pengujian menu Laporan Keuangan & Operasional (/laporan) khusus Owner.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();
    }

    private function user(string $role): User
    {
        return User::create([
            'name'     => ucfirst($role),
            'email'    => "{$role}@laundry.test",
            'password' => 'rahasia123',
            'role'     => $role,
        ]);
    }

    public function test_hanya_owner_yang_bisa_membuka_laporan(): void
    {
        // Tamu (belum login)
        $this->get('/laporan')->assertRedirect('/kasir/masuk');

        // Kasir ditolak (403 Forbidden)
        $this->actingAs($this->user('kasir'))->get('/laporan')->assertForbidden();

        // Operator ditolak (403 Forbidden)
        $this->actingAs($this->user('operator'))->get('/laporan')->assertForbidden();

        // Kurir ditolak (403 Forbidden)
        $this->actingAs($this->user('kurir'))->get('/laporan')->assertForbidden();

        // Owner diizinkan (200 OK)
        $this->actingAs($this->user('owner'))->get('/laporan')->assertOk();
    }

    public function test_laporan_menghitung_arus_kas_laba_rugi_dan_piutang_dengan_benar(): void
    {
        $owner = $this->user('owner');
        $customer = Customer::create(['name' => 'Pak Joko', 'phone' => '081234567890']);

        // Buat Kategori Biaya & Pengeluaran
        $cat = ExpenseCategory::create(['name' => 'Deterjen & Pewangi']);
        Expense::create([
            'expense_category_id' => $cat->id,
            'user_id'             => $owner->id,
            'amount'              => 15000,
            'expense_date'        => now()->toDateString(),
            'description'         => 'Beli sabun 5 liter',
        ]);

        // Buat Order Selesai dan Lunas
        $orderSelesai = Order::create([
            'invoice_number' => 'INV-20261112-001',
            'customer_id'    => $customer->id,
            'user_id'        => $owner->id,
            'order_status'   => 'completed',
            'payment_status' => 'paid',
            'total_amount'   => 40000,
            'discount'       => 0,
            'final_amount'   => 40000,
            'paid_amount'    => 40000,
            'order_date'     => now(),
            'estimate_date'  => now()->addDays(2),
        ]);
        Payment::create([
            'order_id'       => $orderSelesai->id,
            'user_id'        => $owner->id,
            'amount'         => 40000,
            'payment_date'   => now(),
            'payment_method' => 'qris',
            'payment_type'   => 'full_payment',
        ]);

        // Buat Order Belum Lunas (DP)
        $orderDP = Order::create([
            'invoice_number' => 'INV-20261112-002',
            'customer_id'    => $customer->id,
            'user_id'        => $owner->id,
            'order_status'   => 'washing',
            'payment_status' => 'partial',
            'total_amount'   => 50000,
            'discount'       => 0,
            'final_amount'   => 50000,
            'paid_amount'    => 20000,
            'order_date'     => now(),
            'estimate_date'  => now()->addDays(2),
        ]);
        Payment::create([
            'order_id'       => $orderDP->id,
            'user_id'        => $owner->id,
            'amount'         => 20000,
            'payment_date'   => now(),
            'payment_method' => 'cash',
            'payment_type'   => 'down_payment',
        ]);

        // Hitung:
        // Kas Masuk = 40.000 + 20.000 = 60.000
        // Kas Keluar = 15.000
        // Arus Kas Bersih = 60.000 - 15.000 = 45.000
        // Omzet Selesai = 40.000
        // Laba Bersih = 40.000 - 15.000 = 25.000
        // Piutang = 50.000 - 20.000 = 30.000

        $res = $this->actingAs($owner)->get('/laporan');
        $res->assertOk();
        $res->assertSee('Laporan Keuangan &amp; Operasional', false);
        $res->assertSee('60.000'); // Kas Masuk
        $res->assertSee('15.000'); // Kas Keluar
        $res->assertSee('45.000'); // Arus Kas Bersih
        $res->assertSee('40.000'); // Omzet Selesai
        $res->assertSee('25.000'); // Laba Bersih
        $res->assertSee('30.000'); // Piutang Belum Lunas
        $res->assertSee('INV-20261112-002'); // Piutang tampil di tabel
        $res->assertSee('Pak Joko');
        $res->assertSee('Deterjen &amp; Pewangi', false);
    }
}

