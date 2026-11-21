<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\PickupFee;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji menu owner: Layanan & Harga, lokasi laundry, ongkos jemput bertingkat, tema web. */
class OwnerSettingsTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    private const SHOP = 'https://www.google.com/maps?q=-6.238300,106.975600';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();
    }

    private function owner(): User
    {
        return User::create(['name' => 'Owner', 'email' => 'owner@laundry.test', 'password' => 'rahasia123', 'role' => 'owner']);
    }

    private function settings(): Settings
    {
        $this->app->forgetInstance(Settings::class);              // Baca ulang file pengaturan (seperti request baru)
        return app(Settings::class);
    }

    // ---------- Layanan & Harga ----------

    public function test_owner_kelola_kategori_dan_layanan(): void
    {
        $this->actingAs($this->owner());

        $this->post('/kasir/layanan/kategori', ['name' => 'Kiloan'])->assertSessionHasNoErrors();
        $cat = ServiceCategory::first();

        $this->post('/kasir/layanan', [
            'category_id' => $cat->id, 'name' => 'Cuci Setrika', 'unit' => 'kg', 'price' => 8000, 'duration_hours' => 48, 'is_active' => 1,
        ])->assertRedirect(route('kasir.services.index'));
        $svc = Service::first();
        $this->assertEquals(8000, $svc->price);

        // Ubah harga & nonaktifkan lewat form (kotak aktif tidak dicentang → "0")
        $this->put("/kasir/layanan/{$svc->id}", [
            'category_id' => $cat->id, 'name' => 'Cuci Setrika', 'unit' => 'kg', 'price' => 9000, 'duration_hours' => 24, 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $svc->refresh();
        $this->assertEquals(9000, $svc->price);
        $this->assertEquals(0, $svc->is_active);

        // Tombol aktif/nonaktif
        $this->post("/kasir/layanan/{$svc->id}/aktif");
        $this->assertEquals(1, $svc->fresh()->is_active);

        $this->get('/kasir/layanan')->assertOk()->assertSee('Cuci Setrika')->assertSee('Rp9.000');

        // Satuan harus kg/pcs/meter
        $this->post('/kasir/layanan', ['category_id' => $cat->id, 'name' => 'X', 'unit' => 'liter', 'price' => 1, 'duration_hours' => 1])
            ->assertSessionHasErrors('unit');
    }

    public function test_layanan_yang_sudah_dipakai_nota_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->owner());
        $cat = ServiceCategory::create(['name' => 'Kiloan']);
        $dipakai = Service::create(['category_id' => $cat->id, 'name' => 'Dipakai', 'unit' => 'kg', 'price' => 5000, 'duration_hours' => 48, 'is_active' => 1]);
        $baru = Service::create(['category_id' => $cat->id, 'name' => 'Baru', 'unit' => 'kg', 'price' => 5000, 'duration_hours' => 48, 'is_active' => 1]);
        $order = Order::create(['invoice_number' => 'INV-1', 'customer_id' => 1, 'user_id' => 1, 'order_date' => now(), 'estimate_date' => now(),
            'total_amount' => 5000, 'final_amount' => 5000]);
        $order->items()->create(['service_id' => $dipakai->id, 'quantity' => 1, 'unit_price' => 5000, 'subtotal' => 5000]);

        $this->delete("/kasir/layanan/{$dipakai->id}")->assertSessionHasErrors('service');
        $this->delete("/kasir/layanan/{$baru->id}")->assertSessionHasNoErrors();
        $this->assertNotNull(Service::find($dipakai->id));
        $this->assertNull(Service::find($baru->id));

        // Kategori yang masih berisi layanan tidak bisa dihapus
        $this->delete("/kasir/layanan/kategori/{$cat->id}")->assertSessionHasErrors('service');
    }

    public function test_kasir_tidak_bisa_membuka_menu_owner_baru(): void
    {
        $this->actingAs(User::create(['name' => 'K', 'email' => 'k@laundry.test', 'password' => 'rahasia123', 'role' => 'kasir']));
        $this->get('/kasir/layanan')->assertForbidden();
        $this->put('/kasir/pengaturan/tema', ['theme' => 'laut'])->assertForbidden();
        $this->put('/kasir/pengaturan/ongkos', ['rates' => [['max_km' => 1, 'price' => 0]]])->assertForbidden();
    }

    // ---------- Lokasi laundry ----------

    public function test_lokasi_laundry_wajib_berisi_koordinat(): void
    {
        $this->actingAs($this->owner());

        // Link pendek yang titiknya tidak terbaca (Google tidak mengalihkan ke titik) → ditolak
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 404)]);
        $this->put('/kasir/pengaturan/lokasi', ['address' => 'Jl. Pahlawan No. 1, Bekasi', 'maps_url' => 'https://maps.app.goo.gl/abc'])
            ->assertSessionHasErrors('maps_url');

        $this->put('/kasir/pengaturan/lokasi', ['address' => 'Jl. Pahlawan No. 1, Bekasi', 'maps_url' => self::SHOP])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::SHOP, $this->settings()->get('shop_maps_url'));
    }

    // ---------- Ongkos jemput ----------

    public function test_tarif_jemput_bertingkat_disimpan_urut_dan_baris_kosong_dibuang(): void
    {
        $this->actingAs($this->owner());

        $this->put('/kasir/pengaturan/ongkos', ['rates' => [
            ['max_km' => 5, 'price' => 5000], ['max_km' => '', 'price' => ''], ['max_km' => 2, 'price' => 0], ['max_km' => 10, 'price' => 10000],
        ]])->assertSessionHasNoErrors();

        $this->assertEquals([                                    // assertEquals: 2.0 (angka desimal) dianggap sama dengan 2 dari file JSON
            ['max_km' => 2, 'price' => 0], ['max_km' => 5, 'price' => 5000], ['max_km' => 10, 'price' => 10000],
        ], $this->settings()->pickupRates());

        // Jarak dobel ditolak
        $this->put('/kasir/pengaturan/ongkos', ['rates' => [['max_km' => 3, 'price' => 1], ['max_km' => 3, 'price' => 2]]])
            ->assertSessionHasErrors('rates.1.max_km');
    }

    public function test_hitung_ongkos_dari_jarak(): void
    {
        $s = $this->settings();
        $s->set(['shop_maps_url' => self::SHOP, 'pickup_rates' => [['max_km' => 2, 'price' => 0], ['max_km' => 5, 'price' => 5000]]]);
        $fee = new PickupFee($s);

        // ±1,1 km (0,01 derajat lintang) → gratis
        $this->assertSame('ok', $fee->forCustomerUrl('https://www.google.com/maps?q=-6.228300,106.975600')['status']);
        $this->assertSame(0, $fee->forCustomerUrl('https://www.google.com/maps?q=-6.228300,106.975600')['fee']);
        // ±3,3 km → Rp5.000
        $r = $fee->forCustomerUrl('https://www.google.com/maps?q=-6.208300,106.975600');
        $this->assertSame(5000, $r['fee']);
        $this->assertSame('±3,3 km · Rp5.000', PickupFee::describe($r));
        // ±11 km → di luar area
        $this->assertSame('out_of_range', $fee->forCustomerUrl('https://www.google.com/maps?q=-6.138300,106.975600')['status']);
        // Link pendek tanpa koordinat → dihitung admin
        $this->assertSame('unknown', $fee->forCustomerUrl('https://maps.app.goo.gl/xyz')['status']);
    }

    public function test_ongkos_jemput_ikut_di_pesan_wa_reservasi_dan_tabel_beranda(): void
    {
        $this->settings()->set(['shop_maps_url' => self::SHOP, 'pickup_rates' => [['max_km' => 2, 'price' => 0], ['max_km' => 5, 'price' => 5000]]]);
        $c = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now(),
            'address' => 'Jl. Kenanga No. 7, Bekasi', 'maps_url' => 'https://www.google.com/maps?q=-6.208300,106.975600']);

        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', [
            'use_default_address' => 1, 'service' => 'Cuci', 'pickup_date' => now()->addDay()->toDateString(),
        ]);
        $this->assertStringContainsString('Ongkos jemput: ±3,3 km · Rp5.000', rawurldecode($res->headers->get('Location')));

        $this->app->forgetInstance(Settings::class);
        $this->withSession(['customer_id' => $c->id])->get('/')
            ->assertSee('Ongkos antar-jemput')->assertSee('Gratis')
            ->assertSee('Rp5.000 (±3,3 km)');                                     // Ringkasan ongkos di form reservasi (alamat default)
    }

    // ---------- Ongkos langsung terhitung dari link pendek Google Maps ----------

    public function test_link_pendek_dibuka_jadi_koordinat_dan_ongkos_langsung_terhitung(): void
    {
        $this->settings()->set(['shop_maps_url' => self::SHOP, 'pickup_rates' => [['max_km' => 2, 'price' => 0], ['max_km' => 5, 'price' => 5000]]]);
        // Google mengalihkan link pendek → halaman tempat yang berisi titik !3d..!4d..
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Rumah/@-6.2,106.9,17z/data=!3m1!4b1!3d-6.2083!4d106.9756']),
        ]);
        $c = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);

        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', [
            'address' => 'Jl. Kenanga No. 7, Bekasi', 'maps_url' => 'https://maps.app.goo.gl/AbC123',
            'service' => 'Cuci', 'pickup_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertStringContainsString('Ongkos jemput: ±3,3 km · Rp5.000', rawurldecode($res->headers->get('Location')));
        $this->assertSame('https://www.google.com/maps?q=-6.208300,106.975600', $c->fresh()->maps_url);   // Disimpan sebagai link berkoordinat
        $this->assertSame('±3,3 km · Rp5.000', \App\Models\Reservation::first()->pickup_fee);

        // Endpoint untuk form (dipanggil JavaScript saat link pendek ditempel)
        $this->withSession(['customer_id' => $c->id])->getJson('/akun/alamat/buka-link?url=' . urlencode('https://maps.app.goo.gl/AbC123'))
            ->assertJson(['ok' => true, 'url' => 'https://www.google.com/maps?q=-6.208300,106.975600']);
    }

    public function test_buka_link_hanya_ke_google_maps_dan_wajib_masuk(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://contoh-jahat.test/x?q=-6.2,106.9'])]);
        $c = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);

        $this->getJson('/akun/alamat/buka-link?url=' . urlencode('https://maps.app.goo.gl/x'))->assertUnauthorized();
        // Link selain Google Maps ditolak validasi (server tidak membuka alamat bebas)
        $this->withSession(['customer_id' => $c->id])->getJson('/akun/alamat/buka-link?url=' . urlencode('https://contoh-jahat.test/a'))
            ->assertStatus(422);
        // Pengalihan ke luar Google Maps tidak diikuti
        $this->withSession(['customer_id' => $c->id])->getJson('/akun/alamat/buka-link?url=' . urlencode('https://maps.app.goo.gl/x'))
            ->assertJson(['ok' => false]);
        Http::assertSentCount(1);
    }

    public function test_ringkasan_ongkos_di_form_reservasi(): void
    {
        $c = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);

        // Owner belum mengatur lokasi/tarif → dihitung admin
        $this->withSession(['customer_id' => $c->id])->get('/')->assertSee('id="ringkasan-ongkos-nilai"', false)->assertSee('dihitung admin');

        // Sudah diatur, pelanggan belum punya titik → diminta pilih titik
        $this->settings()->set(['shop_maps_url' => self::SHOP, 'pickup_rates' => [['max_km' => 2, 'price' => 0]]]);
        $this->app->forgetInstance(Settings::class);
        $this->withSession(['customer_id' => $c->id])->get('/')->assertSee('pilih titik lokasi dulu');
    }

    // ---------- Tema ----------

    public function test_ganti_tema_berlaku_di_semua_halaman(): void
    {
        $this->actingAs($this->owner());
        $this->put('/kasir/pengaturan/tema', ['theme' => 'laut'])->assertSessionHasNoErrors();
        $this->put('/kasir/pengaturan/tema', ['theme' => 'pelangi'])->assertSessionHasErrors('theme');

        $this->app->forgetInstance(Settings::class);
        $this->get('/')->assertSee('--teal: #2a78b0', false)->assertSee('content="#d6ebf8"', false);   // Beranda
        $this->get('/daftar')->assertSee('--teal: #2a78b0', false);                                    // Daftar
        $this->get('/kasir/pengaturan')->assertSee('--teal: #2a78b0', false)->assertSee('Biru Laut');  // Area Kasir
    }

    // ---------- Rekening Transfer & Gambar QRIS pada Nota Belum Bayar ----------

    public function test_owner_atur_rekening_dan_qris_serta_tampil_di_nota_belum_lunas(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $qrisFile = UploadedFile::fake()->createWithContent(
            'qris.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );

        // Simpan 2 rekening + 1 baris kosong (diabaikan) + gambar QRIS
        $this->put('/kasir/pengaturan/pembayaran', [
            'accounts' => [
                ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Bersih Laundry'],
                ['bank' => '', 'number' => '', 'holder' => ''],
                ['bank' => 'Mandiri', 'number' => '9876543210', 'holder' => 'PT Bersih Jaya'],
            ],
            'qris_image' => $qrisFile,
        ])->assertSessionHasNoErrors();

        $s = $this->settings();
        $this->assertCount(2, $s->bankAccounts());
        $this->assertSame('BCA', $s->bankAccounts()[0]['bank']);
        $this->assertSame('1234567890', $s->bankAccounts()[0]['number']);
        $this->assertNotNull($s->qrisImage());
        $this->assertStringStartsWith('data:image/', $s->qrisImage());

        // Buat pelanggan & 2 nota: 1 belum bayar (unpaid), 1 sudah lunas (paid)
        $customer = Customer::create([
            'name' => 'Siti',
            'phone' => '081299998888',
            'phone_verified_at' => now(),
        ]);

        $unpaidOrder = Order::create([
            'invoice_number' => 'INV-UNPAID-01',
            'customer_id'    => $customer->id,
            'user_id'        => $owner->id,
            'order_date'     => now(),
            'estimate_date'  => now()->addDay(),
            'total_amount'   => 25000,
            'final_amount'   => 25000,
            'paid_amount'    => 0,
            'payment_status' => 'unpaid',
            'order_status'   => 'received',
        ]);

        $paidOrder = Order::create([
            'invoice_number' => 'INV-PAID-01',
            'customer_id'    => $customer->id,
            'user_id'        => $owner->id,
            'order_date'     => now(),
            'estimate_date'  => now()->addDay(),
            'total_amount'   => 25000,
            'final_amount'   => 25000,
            'paid_amount'    => 25000,
            'payment_status' => 'paid',
            'order_status'   => 'received',
        ]);

        // 1. Struk cetak nota belum bayar menampilkan nomor rekening & QRIS
        $this->get("/kasir/transaksi/{$unpaidOrder->id}/cetak")
            ->assertOk()
            ->assertSee('Transfer Pembayaran:')
            ->assertSee('BCA 1234567890')
            ->assertSee('a.n. Bersih Laundry')
            ->assertSee('Mandiri 9876543210')
            ->assertSee('Pembayaran QRIS')
            ->assertSee($s->qrisImage(), false);

        // 2. Struk cetak nota yang sudah LUNAS tidak menampilkan bagian tagihan transfer/QRIS
        $this->get("/kasir/transaksi/{$paidOrder->id}/cetak")
            ->assertOk()
            ->assertDontSee('Transfer Pembayaran:')
            ->assertDontSee('Pembayaran QRIS');

        // 3. Detail nota kasir (belum lunas) menampilkan info rekening, QRIS, dan pesan WA berisi nomor rekening
        $this->get("/kasir/transaksi/{$unpaidOrder->id}")
            ->assertOk()
            ->assertSee('Rekening Transfer Pembayaran:')
            ->assertSee('1234567890')
            ->assertSee('QRIS Pembayaran:')
            ->assertSee(rawurlencode('BCA 1234567890 a.n. Bersih Laundry'), false);

        // 4. Lacak Nota pelanggan (belum lunas) menampilkan nomor rekening & gambar QRIS
        $this->withSession(['customer_id' => $customer->id])
            ->get('/?invoice=INV-UNPAID-01')
            ->assertOk()
            ->assertSee('Pembayaran Nota (Sisa Rp25.000)')
            ->assertSee('1234567890')
            ->assertSee('9876543210')
            ->assertSee('Pindai QRIS untuk membayar')
            ->assertSee($s->qrisImage(), false);

        // 5. Lacak Nota pelanggan yang sudah LUNAS tidak menampilkan kotak tagihan transfer/QRIS
        $this->withSession(['customer_id' => $customer->id])
            ->get('/?invoice=INV-PAID-01')
            ->assertOk()
            ->assertDontSee('Pindai QRIS untuk membayar');

        // 6. Owner dapat menghapus gambar QRIS
        $this->delete('/kasir/pengaturan/qris')->assertSessionHasNoErrors();
        $this->assertNull($this->settings()->qrisImage());
    }

    // ---------- Log Aktivitas Semua User (Owner) ----------

    public function test_log_aktivitas_mencatat_semua_role_dan_hanya_bisa_dibuka_owner(): void
    {
        $owner = $this->owner();
        $kasir = User::create(['name' => 'Rina Kasir', 'email' => 'rina@laundry.test', 'password' => 'rahasia123', 'role' => 'kasir']);
        $operator = User::create(['name' => 'Doni Operator', 'email' => 'doni@laundry.test', 'password' => 'rahasia123', 'role' => 'operator']);
        $customer = Customer::create(['name' => 'Andi Pelanggan', 'phone' => '081277776666', 'phone_verified_at' => now()]);

        // 1. Pelanggan membuat reservasi dari HP Android
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; SM-A546E Build/TP1A) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ])->withSession(['customer_id' => $customer->id])->post('/reservasi', [
            'address' => 'Jl. Melati No. 10, Bekasi',
            'maps_url' => self::SHOP,
            'service' => 'Cuci Setrika',
            'estimated_weight' => 3.5,
            'pickup_date' => now()->addDay()->toDateString(),
        ]);

        // 2. Owner menambah kategori & layanan
        $this->actingAs($owner)->post('/kasir/layanan/kategori', ['name' => 'Kiloan']);
        $cat = ServiceCategory::first();
        $this->actingAs($owner)->post('/kasir/layanan', [
            'category_id' => $cat->id, 'name' => 'Cuci Setrika', 'unit' => 'kg', 'price' => 8000, 'duration_hours' => 48, 'is_active' => 1,
        ]);
        $svc = Service::first();

        // 3. Kasir membuat nota transaksi dari PC Windows
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        ])->actingAs($kasir)->post('/kasir/transaksi', [
            'customer_id' => $customer->id,
            'items' => [['service_id' => $svc->id, 'quantity' => 2]],
            'pay_type' => 'none',
            'payment_method' => 'cash',
        ]);
        $order = Order::first();

        // 4. Operator mengubah status antrean cucian dari iPhone
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ])->actingAs($operator)->post("/kasir/antrean/{$order->id}", ['status' => 'washing']);

        // Kasir tidak boleh membuka halaman Log Aktivitas
        $this->actingAs($kasir)->get('/kasir/log-aktivitas')->assertForbidden();

        // Owner membuka halaman Log Aktivitas dan melihat semua aktivitas beserta Jenis Perangkat & IP Address
        $this->actingAs($owner)->get('/kasir/log-aktivitas')
            ->assertOk()
            ->assertSee('Log Aktivitas Semua User')
            ->assertSee('Jenis Perangkat')
            ->assertSee('IP Address')
            ->assertSee('Andi Pelanggan')
            ->assertSee('Buat Reservasi Jemput')
            ->assertSee('HP / Mobile (Android · Chrome)')
            ->assertSee('Rina Kasir')
            ->assertSee('Buat Nota Baru')
            ->assertSee('PC / Laptop (Windows · Edge)')
            ->assertSee('Doni Operator')
            ->assertSee('Ubah Status Antrean')
            ->assertSee('HP / Mobile (iPhone iOS · Safari)')
            ->assertSee('127.0.0.1');

        // Filter berdasarkan role operator
        $this->actingAs($owner)->get('/kasir/log-aktivitas?role=operator')
            ->assertOk()
            ->assertSee('Doni Operator')
            ->assertDontSee('Andi Pelanggan');

        // Bersihkan log lama
        \App\Models\ActivityLog::first()->update(['created_at' => now()->subDays(100)]);
        $beforeCount = \App\Models\ActivityLog::count();
        $this->actingAs($owner)->delete('/kasir/log-aktivitas/lama', ['days' => 90])
            ->assertRedirect(route('kasir.activity-logs.index'));
        $this->assertLessThan($beforeCount, \App\Models\ActivityLog::count());
    }
}
