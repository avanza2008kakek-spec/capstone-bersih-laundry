<?php

namespace Tests\Feature;

use App\Models\CourierPhoto;
use App\Models\CourierTask;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\PhotoCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Kurir: penugasan oleh kasir, jemput & antar dengan foto bukti. */
class CourierTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();
    }

    private function staff(string $role, string $name = null): User
    {
        $name ??= ucfirst($role);
        return User::create(['name' => $name, 'email' => strtolower($name) . "@laundry.test", 'password' => 'rahasia123', 'role' => $role]);
    }

    /** Foto bukti palsu: PNG 1x1 asli (tanpa ekstensi GD). */
    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Kenanga No. 7, Bekasi',
            'maps_url' => 'https://www.google.com/maps?q=-6.2083,106.9756']);
    }

    private function order(Customer $c, array $attrs = []): Order
    {
        return Order::create($attrs + ['invoice_number' => 'INV-20261107-001', 'customer_id' => $c->id, 'user_id' => 1,
            'order_date' => now(), 'estimate_date' => now(), 'total_amount' => 30000, 'final_amount' => 30000,
            'paid_amount' => 10000, 'payment_status' => 'partial', 'order_status' => 'ready_for_pickup']);
    }

    public function test_owner_bisa_membuat_akun_kurir(): void
    {
        $this->actingAs($this->staff('owner'));
        $this->post('/kasir/staf', ['name' => 'Andi Kurir', 'email' => 'andi@laundry.test', 'role' => 'kurir',
            'password' => 'Rahasia123!', 'password_confirmation' => 'Rahasia123!'])->assertSessionHasNoErrors();
        $this->assertSame('kurir', User::where('email', 'andi@laundry.test')->value('role'));
    }

    public function test_jemput_dari_reservasi_dengan_foto(): void
    {
        $c = $this->customer();
        $r = Reservation::create(['customer_id' => $c->id, 'service' => 'Cuci Setrika', 'pickup_date' => now(), 'address' => $c->address, 'maps_url' => $c->maps_url]);
        $kurir = $this->staff('kurir', 'Andi');
        $kasir = $this->staff('kasir');

        // Kasir menugaskan → reservasi otomatis dikonfirmasi
        $this->actingAs($kasir)->post("/kasir/reservasi/{$r->id}/kurir", ['courier_id' => $kurir->id, 'notes' => 'Bawa kantong besar'])
            ->assertSessionHasNoErrors();
        $task = CourierTask::first();
        $this->assertSame('pickup', $task->type);
        $this->assertSame('confirmed', $r->fresh()->status);
        // Tidak bisa ditugaskan dua kali
        $this->post("/kasir/reservasi/{$r->id}/kurir", ['courier_id' => $kurir->id])->assertSessionHasErrors('courier');

        // Kurir melihat tugasnya + lencana, berangkat, lalu selesai dengan foto
        $this->actingAs($kurir)->get('/kasir/tugas-saya')->assertOk()->assertSee('Budi')->assertSee('Bawa kantong besar')
            ->assertSee('<span class="resv-badge ">1</span>', false);
        $this->getJson('/kasir/tugas-saya/cek')->assertJson(['count' => 1, 'latest' => ['id' => $task->id, 'service' => 'Jemput']]);
        $this->post("/kasir/tugas-saya/{$task->id}/berangkat");
        $this->assertSame('on_the_way', $task->fresh()->status);

        $this->post("/kasir/tugas-saya/{$task->id}/selesai", [])->assertSessionHasErrors('photo');          // Foto wajib
        $this->post("/kasir/tugas-saya/{$task->id}/selesai", ['photo' => $this->photo()])
            ->assertRedirect('/kasir/tugas-saya');

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame('picked_up', $r->fresh()->status);                                                 // Reservasi "Sudah dijemput"

        // Foto tersimpan di database (bukan file), ukurannya kecil
        $photo = CourierPhoto::where('courier_task_id', $task->id)->first();
        $this->assertNotNull($photo);
        $this->assertLessThanOrEqual(PhotoCompressor::MAX_BYTES, $photo->size);
        $this->assertSame([], Storage::disk('local')->allFiles());

        // Foto bisa dilihat kasir & kurirnya (isi dari database), tidak oleh kurir lain
        $res = $this->actingAs($kasir)->get("/kasir/tugas-kurir/{$task->id}/foto")->assertOk();
        $this->assertStringStartsWith('image/', $res->headers->get('Content-Type'));
        $this->assertSame($photo->size, strlen($res->getContent()));
        $this->actingAs($this->staff('kurir', 'Lain'))->get("/kasir/tugas-kurir/{$task->id}/foto")->assertForbidden();
    }

    public function test_owner_menghapus_foto_satu_per_satu_dan_foto_lama(): void
    {
        $c = $this->customer();
        $kurir = $this->staff('kurir', 'Andi');
        $owner = $this->staff('owner');
        $mk = function (int $daysAgo) use ($c, $kurir, $owner) {
            $t = CourierTask::create(['type' => 'pickup', 'customer_id' => $c->id, 'courier_id' => $kurir->id, 'assigned_by' => $owner->id,
                'scheduled_date' => now(), 'address' => $c->address, 'status' => 'done']);
            $p = CourierPhoto::create(['courier_task_id' => $t->id, 'mime' => 'image/png', 'size' => 1000, 'data' => str_repeat('x', 1000)]);
            $p->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
            return $t;
        };
        $baru = $mk(1);
        $lama1 = $mk(100);
        $lama2 = $mk(200);

        // Kasir tidak boleh menghapus / membuka menu Foto Bukti
        $this->actingAs($this->staff('kasir'))->delete("/kasir/tugas-kurir/{$baru->id}/foto")->assertForbidden();
        $this->get('/kasir/foto-bukti')->assertForbidden();

        $this->actingAs($owner)->get('/kasir/foto-bukti')->assertOk()->assertSee('Jumlah foto')->assertSee('3 KB');

        // Hapus foto lebih lama dari 90 hari → 2 foto terhapus, tugasnya tetap ada
        $this->delete('/kasir/foto-bukti/lama', ['days' => 90])->assertSessionHas('status');
        $this->assertSame(1, CourierPhoto::count());
        $this->assertSame(3, CourierTask::count());
        $this->delete('/kasir/foto-bukti/lama', ['days' => 5])->assertSessionHasErrors('days');                 // Hanya pilihan yang ada

        // Hapus satu foto
        $this->delete("/kasir/tugas-kurir/{$baru->id}/foto");
        $this->assertSame(0, CourierPhoto::count());
        $this->get("/kasir/tugas-kurir/{$baru->id}/foto")->assertNotFound();
        $this->get('/kasir/tugas-kurir?status=done')->assertSee('Foto bukti sudah dihapus');
    }

    public function test_halaman_kurir_ada_peta_dan_navigasi(): void
    {
        $c = $this->customer();
        $kurir = $this->staff('kurir', 'Andi');
        $c2 = Customer::create(['name' => 'Siti', 'phone' => '085712345678', 'address' => 'Perum Harapan Indah Blok C2']);   // Tanpa titik peta
        foreach ([[$c, $c->maps_url], [$c2, null]] as [$cust, $maps]) {
            CourierTask::create(['type' => 'pickup', 'customer_id' => $cust->id, 'courier_id' => $kurir->id, 'assigned_by' => 1,
                'scheduled_date' => now(), 'address' => $cust->address, 'maps_url' => $maps]);
        }

        $this->actingAs($kurir)->get('/kasir/tugas-saya')->assertOk()
            ->assertSee('id="peta-kurir"', false)
            ->assertSee('Navigasi ke tujuan')
            // Ada titik → navigasi ke koordinat (mode motor); tanpa titik → navigasi memakai alamat
            ->assertSee('destination=-6.2083%2C106.9756&amp;travelmode=two-wheeler&amp;dir_action=navigate', false)
            ->assertSee('destination=Perum%20Harapan%20Indah%20Blok%20C2', false)
            ->assertSee('tanpa titik peta');
    }

    public function test_antar_nota_kurir_menagih_sisa_pembayaran(): void
    {
        $c = $this->customer();
        $order = $this->order($c);
        $kurir = $this->staff('kurir', 'Andi');

        $this->actingAs($this->staff('kasir'))->post("/kasir/transaksi/{$order->id}/kurir", [
            'courier_id' => $kurir->id, 'scheduled_date' => now()->toDateString(), 'address' => '',
        ])->assertSessionHasNoErrors();
        $task = CourierTask::first();
        $this->assertSame('delivery', $task->type);
        $this->assertSame('Jl. Kenanga No. 7, Bekasi', $task->address);                                    // Alamat pelanggan dipakai
        $this->get("/kasir/transaksi/{$order->id}")->assertSee('Antar via kurir')->assertSee('Andi');

        $this->actingAs($kurir)->get('/kasir/tugas-saya')->assertSee('Tagih ke pelanggan')->assertSee('Rp20.000');

        // Belum centang "uang sudah diterima" → ditolak
        $this->post("/kasir/tugas-saya/{$task->id}/selesai", ['photo' => $this->photo(), 'payment_method' => 'cash'])
            ->assertSessionHasErrors('paid');

        $this->post("/kasir/tugas-saya/{$task->id}/selesai", ['photo' => $this->photo(), 'paid' => 1, 'payment_method' => 'qris'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('completed', $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertEquals(30000, $order->paid_amount);
        $p = Payment::first();
        $this->assertEquals(20000, $p->amount);
        $this->assertSame('qris', $p->payment_method);
        $this->assertSame($kurir->id, (int) $p->user_id);
    }

    public function test_gagal_lalu_ditugaskan_ulang_dan_hak_akses(): void
    {
        $c = $this->customer();
        $order = $this->order($c, ['paid_amount' => 30000, 'payment_status' => 'paid']);
        $andi = $this->staff('kurir', 'Andi');
        $budi = $this->staff('kurir', 'Joko');
        $kasir = $this->staff('kasir');

        $this->actingAs($kasir)->post("/kasir/transaksi/{$order->id}/kurir", ['courier_id' => $andi->id, 'scheduled_date' => now()->toDateString()]);
        $task = CourierTask::first();

        // Kurir lain tidak bisa menyelesaikan tugas Andi
        $this->actingAs($budi)->post("/kasir/tugas-saya/{$task->id}/gagal", ['reason' => 'Rumah kosong'])->assertForbidden();

        $this->actingAs($andi)->post("/kasir/tugas-saya/{$task->id}/gagal", ['reason' => 'Rumah kosong, telepon tidak diangkat']);
        $this->assertSame('failed', $task->fresh()->status);
        $this->assertSame('ready_for_pickup', $order->fresh()->order_status);

        // Kasir melihat di tab Gagal dan menugaskan ulang ke kurir lain
        $this->actingAs($kasir)->get('/kasir/tugas-kurir?status=failed')->assertSee('Rumah kosong, telepon tidak diangkat');
        $this->post("/kasir/transaksi/{$order->id}/kurir", ['courier_id' => $budi->id, 'scheduled_date' => now()->addDay()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, CourierTask::count());

        // Ganti kurir & batalkan
        $baru = CourierTask::latest('id')->first();
        $this->put("/kasir/tugas-kurir/{$baru->id}/kurir", ['courier_id' => $andi->id]);
        $this->assertSame($andi->id, (int) $baru->fresh()->courier_id);
        $this->put("/kasir/tugas-kurir/{$baru->id}/kurir", ['courier_id' => $kasir->id])->assertSessionHasErrors('courier_id'); // Bukan kurir
        $this->post("/kasir/tugas-kurir/{$baru->id}/batal");
        $this->assertSame('cancelled', $baru->fresh()->status);

        // Hak akses menu
        $this->actingAs($andi);
        $this->get('/kasir/antrean')->assertForbidden();
        $this->get('/kasir/reservasi')->assertForbidden();
        $this->get('/kasir/tugas-kurir')->assertForbidden();
        $this->get('/kasir')->assertOk()->assertSee('Tugas Saya')->assertDontSee('Antrean Cucian');
        $this->actingAs($kasir)->get('/kasir/tugas-saya')->assertForbidden();
    }

    public function test_kurir_diberi_tahu_lewat_wa_saat_ditugaskan(): void
    {
        $c = $this->customer();
        $order = $this->order($c);                                       // Sisa tagihan Rp20.000
        $kurir = $this->staff('kurir', 'Andi');
        $kurir->update(['phone' => '0813-1111-2222']);
        $tanpaHp = $this->staff('kurir', 'Joko');
        $kasir = $this->staff('kasir');

        $res = $this->actingAs($kasir)->from("/kasir/transaksi/{$order->id}")->post("/kasir/transaksi/{$order->id}/kurir", [
            'courier_id' => $kurir->id, 'scheduled_date' => now()->toDateString(), 'notes' => 'Hati-hati, ada jas',
        ]);
        $wa = session('courier_wa');
        $this->assertSame('Andi', $wa['name']);
        $this->assertStringStartsWith('https://wa.me/6281311112222?text=', $wa['link']);
        $pesan = rawurldecode(substr($wa['link'], strpos($wa['link'], '=') + 1));
        foreach (['Halo Andi', '*ANTAR cucian*', 'Pelanggan: Budi', 'Alamat: Jl. Kenanga No. 7, Bekasi', 'Nota: INV-20261107-001',
                  'Tagih ke pelanggan: Rp20.000', 'Pesan kasir: Hati-hati, ada jas', 'travelmode=two-wheeler', '/kasir/tugas-saya'] as $isi) {
            $this->assertStringContainsString($isi, $pesan);
        }
        // Tombol tampil di halaman setelah kembali
        $this->get($res->headers->get('Location'))->assertSee('Kirim WA ke kurir');
        $this->get('/kasir/tugas-kurir')->assertSee('WA kurir');

        // Ganti ke kurir tanpa No. HP → tidak ada link, ada keterangan
        $task = CourierTask::first();
        $this->put("/kasir/tugas-kurir/{$task->id}/kurir", ['courier_id' => $tanpaHp->id]);
        $this->assertNull(session('courier_wa')['link']);
        $this->get('/kasir/tugas-kurir')->assertSee('No. HP kurir kosong');
    }

    public function test_nota_belum_siap_tidak_bisa_diantar(): void
    {
        $c = $this->customer();
        $order = $this->order($c, ['order_status' => 'washing']);
        $this->actingAs($this->staff('kasir'))->post("/kasir/transaksi/{$order->id}/kurir", [
            'courier_id' => $this->staff('kurir', 'Andi')->id, 'scheduled_date' => now()->toDateString(),
        ])->assertSessionHasErrors('courier');
        $this->assertSame(0, CourierTask::count());
    }

    public function test_foto_serah_terima_jemputan_ke_kasir_dan_kasir_ke_kurir(): void
    {
        $c = $this->customer();
        $r = Reservation::create(['customer_id' => $c->id, 'service' => 'Cuci Setrika', 'pickup_date' => now(), 'address' => $c->address]);
        $kurir = $this->staff('kurir', 'Andi');
        $kasir = $this->staff('kasir');

        // 1. Tugas jemput: kurir jemput dari pelanggan (foto 1), lalu serahkan ke kasir di outlet (foto 2)
        $this->actingAs($kasir)->post("/kasir/reservasi/{$r->id}/kurir", ['courier_id' => $kurir->id]);
        $pickupTask = CourierTask::first();
        $this->actingAs($kurir)->post("/kasir/tugas-saya/{$pickupTask->id}/selesai", ['photo' => $this->photo()]);

        // Muncul di daftar "Serahkan Cucian Jemputan ke Kasir" di halaman kurir
        $this->get('/kasir/tugas-saya')->assertSee('Serahkan Cucian Jemputan ke Kasir');

        // Kurir menyerahkan ke kasir dengan foto kamera
        $this->post("/kasir/tugas-saya/{$pickupTask->id}/serah-kasir", ['photo' => $this->photo()])
            ->assertRedirect('/kasir/tugas-saya');
        $pickupTask->refresh();
        $this->assertNotNull($pickupTask->handover_at);
        $this->assertNotNull($pickupTask->handoverPhoto);
        $this->assertNotNull($pickupTask->photo);
        $this->get("/kasir/tugas-kurir/{$pickupTask->id}/foto?kind=handover")->assertOk();

        // 2. Tugas antar: kasir menyerahkan cucian ke kurir dengan foto kamera, lalu kurir mengantar ke pelanggan dengan foto kamera
        $order = $this->order($c, ['paid_amount' => 30000, 'payment_status' => 'paid']);
        $this->actingAs($kasir)->post("/kasir/transaksi/{$order->id}/kurir", [
            'courier_id'     => $kurir->id,
            'scheduled_date' => now()->toDateString(),
            'handover_photo' => $this->photo(),
        ])->assertSessionHasNoErrors();

        $deliveryTask = CourierTask::where('type', 'delivery')->first();
        $this->assertNotNull($deliveryTask->handover_at);
        $this->assertNotNull($deliveryTask->handoverPhoto);

        // Kurir melihat foto serah dari kasir di halaman Tugas Saya, lalu mengantar & mengambil foto di pelanggan
        $this->actingAs($kurir)->get('/kasir/tugas-saya')->assertSee('Lihat foto serah dari kasir');
        $this->post("/kasir/tugas-saya/{$deliveryTask->id}/selesai", ['photo' => $this->photo()])
            ->assertRedirect('/kasir/tugas-saya');

        $deliveryTask->refresh();
        $this->assertNotNull($deliveryTask->photo);
        $this->assertNotNull($deliveryTask->handoverPhoto);
        $this->actingAs($kasir)->get("/kasir/tugas-kurir/{$deliveryTask->id}/foto")->assertOk();
        $this->get("/kasir/tugas-kurir/{$deliveryTask->id}/foto?kind=handover")->assertOk();
    }
}
