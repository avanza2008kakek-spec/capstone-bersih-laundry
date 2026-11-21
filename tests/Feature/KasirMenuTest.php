<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji menu kasir: Transaksi, Antrean Cucian, Pelanggan, Pengeluaran, Dashboard. */
class KasirMenuTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    private Service $kiloan;
    private Service $setrika;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();
        DB::table('service_categories')->insert(['id' => 1, 'name' => 'Kiloan']);
        $this->kiloan  = Service::create(['category_id' => 1, 'name' => 'Cuci Kering Lipat', 'unit' => 'kg', 'price' => 6000, 'duration_hours' => 48, 'is_active' => 1]);
        $this->setrika = Service::create(['category_id' => 1, 'name' => 'Cuci Setrika', 'unit' => 'kg', 'price' => 8000, 'duration_hours' => 72, 'is_active' => 1]);
    }

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => "{$role}@laundry.test", 'password' => 'rahasia123', 'role' => $role]);
    }

    private function orderData(array $override = []): array
    {
        return array_merge([
            'new_name' => 'Budi', 'new_phone' => '0812-3456-7890', 'new_address' => 'Jl. Melati 5',
            'items' => [
                ['service_id' => $this->kiloan->id, 'quantity' => 3, 'notes' => 'Baju putih dipisah'],
                ['service_id' => $this->setrika->id, 'quantity' => 2.5],
            ],
            'discount' => 1000, 'notes' => 'Jangan pakai pemutih',
            'pay_type' => 'dp', 'pay_amount' => 10000, 'payment_method' => 'qris',
        ], $override);
    }

    // ---------- Transaksi ----------

    public function test_kasir_membuat_nota_dengan_pelanggan_baru_dan_dp(): void
    {
        $kasir = $this->user('kasir');
        $res = $this->actingAs($kasir)->post('/kasir/transaksi', $this->orderData());

        $order = Order::first();
        $res->assertRedirect(route('kasir.orders.show', $order));
        $this->assertMatchesRegularExpression('/^INV-\d{8}-001$/', $order->invoice_number);

        // 3 x 6.000 + 2,5 x 8.000 = 38.000; diskon 1.000 → 37.000; DP 10.000
        $this->assertEquals(38000, $order->total_amount);
        $this->assertEquals(1000, $order->discount);
        $this->assertEquals(37000, $order->final_amount);
        $this->assertEquals(10000, $order->paid_amount);
        $this->assertSame('partial', $order->payment_status);
        $this->assertSame('received', $order->order_status);
        $this->assertSame($kasir->id, $order->user_id);
        $this->assertEqualsWithDelta(now()->addHours(72)->timestamp, $order->estimate_date->timestamp, 5); // Layanan terlama 72 jam

        $this->assertSame('081234567890', $order->customer->phone);              // HP disimpan format 08xx
        $this->assertSame(2, $order->items()->count());
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 10000, 'payment_method' => 'qris', 'payment_type' => 'down_payment']);
        $this->assertDatabaseHas('order_status_logs', ['order_id' => $order->id, 'status' => 'received', 'user_id' => $kasir->id]);
    }

    public function test_nomor_nota_berurutan_dan_pelanggan_lama_dipakai_ulang(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $this->post('/kasir/transaksi', $this->orderData(['new_phone' => '+62 812 3456 7890'])); // Nomor sama, format beda

        $this->assertMatchesRegularExpression('/-002$/', Order::orderByDesc('id')->first()->invoice_number);
        $this->assertSame(1, Customer::count());
    }

    public function test_lunas_dan_bayar_nanti(): void
    {
        $this->actingAs($this->user('kasir'));

        $this->post('/kasir/transaksi', $this->orderData(['pay_type' => 'full', 'pay_amount' => null]));
        $lunas = Order::first();
        $this->assertSame('paid', $lunas->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $lunas->id, 'amount' => 37000, 'payment_type' => 'full_payment']);

        $this->post('/kasir/transaksi', $this->orderData(['pay_type' => 'none', 'pay_amount' => null]));
        $nanti = Order::orderByDesc('id')->first();
        $this->assertSame('unpaid', $nanti->payment_status);
        $this->assertSame(0, $nanti->payments()->count());
    }

    public function test_harga_selalu_dari_database_dan_diskon_tidak_melebihi_total(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData([
            'items' => [['service_id' => $this->kiloan->id, 'quantity' => 1, 'unit_price' => 1]], // unit_price dari browser diabaikan
            'discount' => 99999, 'pay_type' => 'none',
        ]));

        $order = Order::first();
        $this->assertEquals(6000, $order->items->first()->unit_price);
        $this->assertEquals(6000, $order->discount);                             // Dibatasi sebesar total
        $this->assertEquals(0, $order->final_amount);
    }

    public function test_validasi_nota(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData(['new_name' => null, 'new_phone' => null]))->assertSessionHasErrors(['new_name', 'new_phone']);
        $this->post('/kasir/transaksi', $this->orderData(['items' => []]))->assertSessionHasErrors('items');
        $this->post('/kasir/transaksi', $this->orderData(['pay_amount' => null]))->assertSessionHasErrors('pay_amount');

        $this->kiloan->update(['is_active' => 0]);                               // Layanan nonaktif tidak bisa dipilih
        $this->post('/kasir/transaksi', $this->orderData())->assertSessionHasErrors('items.0.service_id');
        $this->assertSame(0, Order::count());
    }

    public function test_pelunasan_dan_tidak_boleh_melebihi_sisa(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();                                                 // Sisa 27.000

        $this->post("/kasir/transaksi/{$order->id}/bayar", ['amount' => 30000, 'payment_method' => 'cash'])->assertSessionHasErrors('amount');
        $this->post("/kasir/transaksi/{$order->id}/bayar", ['amount' => 27000, 'payment_method' => 'cash'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertEquals(37000, $order->paid_amount);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 27000, 'payment_type' => 'settlement']);
    }

    public function test_diambil_hanya_jika_siap_dan_lunas(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();

        $this->post("/kasir/transaksi/{$order->id}/diambil")->assertSessionHasErrors('pickup');  // Belum siap
        $order->update(['order_status' => 'ready_for_pickup']);
        $this->post("/kasir/transaksi/{$order->id}/diambil")->assertSessionHasErrors('pickup');  // Belum lunas

        $this->post("/kasir/transaksi/{$order->id}/bayar", ['amount' => 27000, 'payment_method' => 'cash']);
        $this->post("/kasir/transaksi/{$order->id}/diambil")->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->completion_date);
    }

    public function test_detail_nota_cetak_dan_link_wa(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();

        $this->get("/kasir/transaksi/{$order->id}")->assertOk()
            ->assertSee($order->invoice_number)->assertSee('Cuci Kering Lipat')
            ->assertSee('https://wa.me/6281234567890?text=', false);
        $this->get("/kasir/transaksi/{$order->id}/cetak")->assertOk()->assertSee('Bersih Laundry')->assertSee('37.000');
        $this->get('/kasir/transaksi?q=Budi')->assertOk()->assertSee($order->invoice_number);
        $this->get('/kasir/transaksi/baru')->assertOk()->assertSee('Cuci Kering Lipat');
    }

    public function test_nota_cetak_berisi_qr_lacak_dan_barcode_nomor_nota(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();

        $lacak = route('customer.home', ['invoice' => $order->invoice_number]) . '#lacak';
        $this->get("/kasir/transaksi/{$order->id}/cetak")->assertOk()
            ->assertSee('id="qr"', false)
            ->assertSee('data-text="' . e($lacak) . '"', false)                    // QR = link Lacak Nota
            ->assertSee('data-text="' . $order->invoice_number . '"', false)       // Barcode = nomor nota
            ->assertSee('qrcode-generator', false)
            ->assertSee('JsBarcode', false);
    }

    public function test_pindai_barcode_di_transaksi_langsung_membuka_nota(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();

        // Alat pindai mengetik nomor nota lalu Enter (huruf kecil pun tetap cocok)
        $this->get('/kasir/transaksi?q=' . $order->invoice_number)->assertRedirect(route('kasir.orders.show', $order));
        $this->get('/kasir/transaksi?q=' . strtolower($order->invoice_number))->assertRedirect(route('kasir.orders.show', $order));

        // Pencarian sebagian tetap menampilkan daftar
        $this->get('/kasir/transaksi?q=INV-')->assertOk()->assertSee($order->invoice_number);
    }

    // ---------- Antrean Cucian ----------

    public function test_operator_memajukan_status_dan_tercatat(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();

        $op = $this->user('operator');
        $this->actingAs($op)->get('/kasir/antrean')->assertOk()->assertSee($order->invoice_number);
        $this->post("/kasir/antrean/{$order->id}", ['status' => 'washing'])->assertSessionHasNoErrors();
        $this->assertSame('washing', $order->fresh()->order_status);
        $this->assertDatabaseHas('order_status_logs', ['order_id' => $order->id, 'status' => 'washing', 'user_id' => $op->id]);

        // Lewati langsung ke "Siap diambil"
        $this->post("/kasir/antrean/{$order->id}", ['status' => 'ready_for_pickup']);
        $this->assertSame('ready_for_pickup', $order->fresh()->order_status);

        // "Sudah diambil" tidak bisa dari antrean
        $this->post("/kasir/antrean/{$order->id}", ['status' => 'completed'])->assertSessionHasErrors('status');
    }

    public function test_kembali_ke_tahap_sebelumnya_jika_salah_pencet(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());
        $order = Order::first();
        $order->update(['order_status' => 'drying']);

        // Kartu menampilkan tombol kembali ke tahap sebelumnya
        $this->get('/kasir/antrean')->assertSee('kembali ke Dicuci');

        $op = $this->user('operator');
        $this->actingAs($op)->post("/kasir/antrean/{$order->id}", ['status' => 'washing'])
            ->assertSessionHas('status', "{$order->invoice_number} dikembalikan ke Dicuci");

        $this->assertSame('washing', $order->fresh()->order_status);
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id, 'status' => 'washing', 'user_id' => $op->id, 'notes' => 'Dikembalikan dari Dikeringkan',
        ]);

        // Tahap "Diterima" tidak punya tombol kembali
        $order->update(['order_status' => 'received']);
        $this->assertNull($order->fresh()->previousStatus());

        // Nota yang sudah diambil tidak bisa dikembalikan ke antrean
        $order->update(['order_status' => 'completed']);
        $this->post("/kasir/antrean/{$order->id}", ['status' => 'ready_for_pickup'])->assertSessionHasErrors('queue');
    }

    public function test_operator_tidak_bisa_buka_transaksi_pelanggan_pengeluaran(): void
    {
        $this->actingAs($this->user('operator'));
        $this->get('/kasir/transaksi')->assertForbidden();
        $this->get('/kasir/pelanggan')->assertForbidden();
        $this->get('/kasir/pengeluaran')->assertForbidden();
        $this->get('/kasir')->assertOk()->assertDontSee('Uang masuk hari ini');
    }

    // ---------- Pelanggan ----------

    public function test_cari_tambah_ubah_pelanggan(): void
    {
        $this->actingAs($this->user('kasir'));

        $this->post('/kasir/pelanggan', ['name' => 'Siti', 'phone' => '0813 1111 2222'])->assertSessionHasNoErrors();
        $siti = Customer::first();
        $this->assertSame('081311112222', $siti->phone);

        // No. HP sama (beda format) ditolak
        $this->post('/kasir/pelanggan', ['name' => 'Siti 2', 'phone' => '6281311112222'])->assertSessionHasErrors('phone');

        // Cari dari form nota
        $this->getJson('/kasir/pelanggan/cari?q=sit')->assertJsonFragment(['name' => 'Siti']);
        $this->getJson('/kasir/pelanggan/cari?q=6281311112222')->assertJsonFragment(['name' => 'Siti']);

        // Ganti HP → status terverifikasi WA dicabut
        $siti->update(['phone_verified_at' => now()]);
        $this->put("/kasir/pelanggan/{$siti->id}", ['name' => 'Siti A', 'phone' => '081399998888'])->assertSessionHasNoErrors();
        $this->assertNull($siti->fresh()->phone_verified_at);

        $this->get("/kasir/pelanggan/{$siti->id}")->assertOk()->assertSee('Siti A');
        $this->get('/kasir/pelanggan?q=Siti')->assertOk()->assertSee('081399998888');
    }

    // ---------- Pengeluaran ----------

    public function test_kasir_mencatat_pengeluaran_owner_menghapus(): void
    {
        $cat = ExpenseCategory::create(['name' => 'Bahan']);
        $kasir = $this->user('kasir');

        $this->actingAs($kasir)->post('/kasir/pengeluaran', [
            'expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'amount' => 45000, 'description' => 'Deterjen 5 liter',
        ])->assertSessionHasNoErrors();
        $expense = Expense::first();
        $this->assertSame($kasir->id, $expense->user_id);
        $this->get('/kasir/pengeluaran')->assertOk()->assertSee('Deterjen 5 liter')->assertSee('Bahan');

        // Keterangan wajib; kasir tidak bisa hapus & tidak bisa tambah kategori
        $this->post('/kasir/pengeluaran', ['expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'amount' => 1000, 'description' => ''])
            ->assertSessionHasErrors('description');
        $this->delete("/kasir/pengeluaran/{$expense->id}")->assertForbidden();
        $this->post('/kasir/pengeluaran/kategori', ['name' => 'Listrik'])->assertForbidden();

        $this->actingAs($this->user('owner'));
        $this->post('/kasir/pengeluaran/kategori', ['name' => 'Listrik'])->assertSessionHasNoErrors();
        $this->delete("/kasir/pengeluaran/{$expense->id}")->assertRedirect();
        $this->assertSame(0, Expense::count());
        $this->assertSame(2, ExpenseCategory::count());
    }

    // ---------- Dashboard ----------

    public function test_dashboard_kasir_menampilkan_ringkasan_hari_ini(): void
    {
        $this->actingAs($this->user('kasir'));
        $this->post('/kasir/transaksi', $this->orderData());

        $this->get('/kasir')->assertOk()
            ->assertSee('Nota hari ini')->assertSee('Uang masuk hari ini')
            ->assertSee('QRIS Rp10.000', false)
            ->assertSee('Rp27.000');                                             // Belum lunas
    }
}
