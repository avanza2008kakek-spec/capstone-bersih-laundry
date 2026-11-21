<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HubRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji Lacak Nota: hanya pemilik nota (yang masuk dengan nomor WA-nya) yang bisa melihat status cucian. */
class LacakNotaTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    private Customer $budi;
    private Customer $siti;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();

        DB::table('service_categories')->insert(['id' => 1, 'name' => 'Kiloan']);
        $svc = Service::create(['category_id' => 1, 'name' => 'Cuci Kering Lipat', 'unit' => 'kg', 'price' => 5000, 'duration_hours' => 48, 'is_active' => 1]);
        $this->budi = Customer::create(['name' => 'Budi Pemilik', 'phone' => '081234567890', 'phone_verified_at' => now()]);
        $this->siti = Customer::create(['name' => 'Siti Lain', 'phone' => '081399998888', 'phone_verified_at' => now()]);

        $this->order = Order::create([
            'invoice_number' => 'INV-20261106-001', 'customer_id' => $this->budi->id, 'user_id' => 1,
            'order_date' => now(), 'estimate_date' => now()->addDays(2), 'total_amount' => 6000, 'discount' => 0,
            'final_amount' => 6000, 'paid_amount' => 6000, 'payment_status' => 'paid', 'order_status' => 'washing',
        ]);
        $this->order->items()->create(['service_id' => $svc->id, 'quantity' => 1.2, 'unit_price' => 5000, 'subtotal' => 6000]);
    }

    public function test_tamu_harus_masuk_dan_isi_nota_dirahasiakan(): void
    {
        $this->get('/?invoice=INV-20261106-001')
            ->assertOk()
            ->assertSee('Masuk dulu untuk melihat status cucian')
            ->assertDontSee('Budi Pemilik')                                          // Nama pemilik tidak bocor
            ->assertDontSee('Cuci Kering Lipat (1,2')
            ->assertSessionHas('after_login', route('customer.home', ['invoice' => 'INV-20261106-001']) . '#lacak');
    }

    public function test_pemilik_nota_melihat_status_cucian(): void
    {
        $this->withSession(['customer_id' => $this->budi->id])
            ->get('/?invoice=inv-20261106-001')                                      // Huruf kecil tetap cocok
            ->assertOk()
            ->assertSee('Budi Pemilik')
            ->assertSee('Cuci Kering Lipat')
            ->assertDontSee('Masuk dulu untuk melihat status cucian');
    }

    public function test_pelanggan_lain_tidak_bisa_melihat_nota_orang_lain(): void
    {
        $this->withSession(['customer_id' => $this->siti->id])
            ->get('/?invoice=INV-20261106-001')
            ->assertOk()
            ->assertSee('tidak ditemukan di akun Anda')
            ->assertDontSee('Budi Pemilik')
            ->assertDontSee('Cuci Kering Lipat (1,2');
    }

    public function test_nota_tidak_ada_pesannya_sama_dengan_nota_orang_lain(): void
    {
        $this->withSession(['customer_id' => $this->siti->id])
            ->get('/?invoice=INV-20990101-999')
            ->assertSee('tidak ditemukan di akun Anda');
    }

    public function test_daftar_cucian_saya_hanya_nota_milik_sendiri(): void
    {
        $this->withSession(['customer_id' => $this->budi->id])->get('/')->assertSee('Cucian saya')->assertSee('INV-20261106-001 · Dicuci');
        $this->withSession(['customer_id' => $this->siti->id])->get('/')->assertDontSee('INV-20261106-001');
    }

    public function test_scan_qr_lalu_masuk_kode_wa_kembali_ke_nota(): void
    {
        Http::fake(function (HubRequest $r) {
            $this->code = $r['kode_otp'];
            return Http::response(['success' => true, 'request_id' => 'R1', 'kode_otp' => $r['kode_otp']]);
        });

        $this->get('/?invoice=INV-20261106-001');                                   // Scan QR saat belum masuk
        $this->post('/masuk', ['phone' => '081234567890']);
        $this->post('/daftar/verifikasi', ['code' => $this->code])
            ->assertRedirect(route('customer.home', ['invoice' => 'INV-20261106-001']) . '#lacak');

        $this->get('/?invoice=INV-20261106-001')->assertSee('Budi Pemilik');
    }

    public function test_update_status_oleh_operator_langsung_terlihat_di_lacak_pelanggan_dan_endpoint_cek(): void
    {
        $operator = \App\Models\User::create([
            'name' => 'Op', 'email' => 'op@laundry.test', 'password' => 'rahasia123', 'role' => 'operator',
        ]);

        // Operator mengubah status cucian dari Dicuci (washing) menjadi Disetrika (ironing)
        $this->actingAs($operator)->post("/kasir/antrean/{$this->order->id}", ['status' => 'ironing'])
            ->assertSessionHasNoErrors();

        // Endpoint real-time /lacak-cek langsung mengembalikan status Disetrika (step 4)
        $this->withSession(['customer_id' => $this->budi->id])
            ->getJson('/lacak-cek?invoice=INV-20261106-001')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('tracked.order_status', 'ironing')
            ->assertJsonPath('tracked.status_label', 'Disetrika')
            ->assertJsonPath('tracked.step', 4);

        // Halaman Lacak Nota pelanggan menampilkan status terbaru dan header anti-cache
        $res = $this->withSession(['customer_id' => $this->budi->id])
            ->get('/?invoice=INV-20261106-001');
        $res->assertOk()
            ->assertSee('INV-20261106-001 · Disetrika')
            ->assertSee('Status cucian: <strong id="lacak-status-text">Disetrika</strong>', false);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    private string $code = '';
}
