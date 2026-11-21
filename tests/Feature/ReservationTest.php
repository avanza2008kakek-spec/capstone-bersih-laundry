<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HubRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji form Reservasi di halaman depan: wajib masuk, alamat wajib, lokasi Google Maps. */
class ReservationTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    private ?string $sentCode = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Tabel laundry (di hosting dibuat lewat SQL, tanpa migrasi) → dibuat untuk test
        $this->createLaundryTables();

        Http::fake(function (HubRequest $r) {
            $this->sentCode = $r['kode_otp'];
            return Http::response(['success' => true, 'request_id' => 'R1', 'kode_otp' => $r['kode_otp']]);
        });
    }

    private function customer(array $extra = []): Customer
    {
        return Customer::create(array_merge(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()], $extra));
    }

    private function validData(array $override = []): array
    {
        return array_merge([
            'address'     => 'Jl. Melati No. 5, RT 02/RW 03, dekat masjid',
            'maps_url'    => 'https://www.google.com/maps?q=-6.238300,106.975600',
            'service'     => 'Cuci Kering Setrika',
            'pickup_date' => now()->addDay()->toDateString(),
            'notes'       => 'Ada baju luntur',
        ], $override);
    }

    public function test_tamu_melihat_ajakan_masuk_bukan_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Masuk dulu untuk reservasi')
            ->assertSee(route('login.form', ['next' => 'reservasi']), false)
            ->assertDontSee('id="form-reservasi"', false);
    }

    public function test_pelanggan_masuk_melihat_form_dengan_data_terisi(): void
    {
        $c = $this->customer(['address' => 'Jl. Kenanga 7', 'maps_url' => 'https://maps.app.goo.gl/abc123']);

        $this->withSession(['customer_id' => $c->id])->get('/')
            ->assertOk()
            ->assertSee('id="form-reservasi"', false)
            ->assertSee('value="081234567890"', false)
            ->assertSee('Jl. Kenanga 7')
            ->assertSee('https://maps.app.goo.gl/abc123');
    }

    public function test_tamu_kirim_reservasi_diarahkan_ke_masuk(): void
    {
        $this->post('/reservasi', $this->validData())
            ->assertRedirect(route('login.form'))
            ->assertSessionHas('after_login', route('customer.home') . '#pesan');
    }

    public function test_setelah_masuk_dari_tombol_reservasi_kembali_ke_form(): void
    {
        $this->customer();

        $this->get('/masuk?next=reservasi')->assertOk()->assertSee('kembali ke form reservasi');
        $this->post('/masuk', ['phone' => '081234567890']);
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode])
            ->assertRedirect(route('customer.home') . '#pesan');
    }

    public function test_next_hanya_menerima_tujuan_yang_dikenal(): void
    {
        $this->get('/masuk?next=https://situs-jahat.test')->assertSessionMissing('after_login');
    }

    public function test_reservasi_valid_menyimpan_alamat_lokasi_dan_membuka_whatsapp_admin(): void
    {
        $c = $this->customer();

        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', $this->validData());

        $target = $res->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/6282115500412?text=', $target);
        $text = rawurldecode(substr($target, strpos($target, '?text=') + 6));
        $this->assertStringContainsString('Halo Bersih Laundry, saya mau reservasi laundry:', $text);
        $this->assertStringContainsString('Alamat: Jl. Melati No. 5, RT 02/RW 03, dekat masjid', $text);
        $this->assertStringContainsString('Lokasi: https://www.google.com/maps?q=-6.238300,106.975600', $text);
        $this->assertStringContainsString('Catatan: Ada baju luntur', $text);

        $c->refresh();
        $this->assertSame('Jl. Melati No. 5, RT 02/RW 03, dekat masjid', $c->address);
        $this->assertSame('https://www.google.com/maps?q=-6.238300,106.975600', $c->maps_url);
    }

    public function test_alamat_kosong_ditolak_dan_kembali_ke_form(): void
    {
        $c = $this->customer();

        $this->withSession(['customer_id' => $c->id])
            ->post('/reservasi', $this->validData(['address' => '']))
            ->assertRedirect(route('customer.home') . '#pesan')
            ->assertSessionHasErrors(['address' => 'Alamat penjemputan wajib diisi.']);
        $this->assertNull($c->fresh()->address);
    }

    public function test_link_lokasi_bukan_google_maps_ditolak(): void
    {
        $c = $this->customer();

        $this->withSession(['customer_id' => $c->id])
            ->post('/reservasi', $this->validData(['maps_url' => 'https://situs-lain.test/maps?q=1,2']))
            ->assertSessionHasErrors('maps_url');

        // Link pendek dari tombol Bagikan Google Maps diterima
        $this->withSession(['customer_id' => $c->id])
            ->post('/reservasi', $this->validData(['maps_url' => 'https://maps.app.goo.gl/AbCdE12345']))
            ->assertSessionHasNoErrors();
    }

    public function test_lokasi_boleh_kosong_dan_lokasi_lama_tetap_tersimpan(): void
    {
        $c = $this->customer(['maps_url' => 'https://maps.app.goo.gl/lama']);

        $this->withSession(['customer_id' => $c->id])->post('/reservasi', $this->validData(['maps_url' => '']));
        $this->assertSame('https://maps.app.goo.gl/lama', $c->fresh()->maps_url);
    }

    // ---------- Alamat default ----------

    public function test_alamat_dan_lokasi_terisi_menjadi_alamat_default(): void
    {
        $c = $this->customer();
        $this->assertFalse($c->hasDefaultAddress());

        $this->withSession(['customer_id' => $c->id])->post('/reservasi', $this->validData());
        $this->assertTrue($c->fresh()->hasDefaultAddress());

        // Reservasi berikutnya: tampil kartu alamat default, bukan kotak isian
        $this->withSession(['customer_id' => $c->id])->get('/')
            ->assertSee('Alamat default')
            ->assertSee('name="use_default_address"', false)
            ->assertSee(route('address.edit', ['next' => 'reservasi']), false)
            ->assertDontSee('id="r-alamat"', false);
    }

    public function test_alamat_tanpa_lokasi_belum_jadi_default_dan_kotak_isian_tetap_tampil(): void
    {
        $c = $this->customer(['address' => 'Jl. Kenanga No. 7, Bekasi']);

        $this->withSession(['customer_id' => $c->id])->get('/')
            ->assertDontSee('name="use_default_address"', false)
            ->assertSee('id="r-alamat"', false)
            ->assertSee('Jl. Kenanga No. 7, Bekasi');
    }

    public function test_reservasi_memakai_alamat_default_tanpa_mengisi_ulang(): void
    {
        $c = $this->customer(['address' => 'Jl. Kenanga No. 7, Bekasi', 'maps_url' => 'https://maps.app.goo.gl/rumah']);

        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', [
            'use_default_address' => '1',
            'service' => 'Cuci Kering Lipat', 'pickup_date' => now()->addDay()->toDateString(),
        ]);

        $text = rawurldecode($res->headers->get('Location'));
        $this->assertStringContainsString('Alamat: Jl. Kenanga No. 7, Bekasi', $text);
        $this->assertStringContainsString('Lokasi: https://maps.app.goo.gl/rumah', $text);
    }

    public function test_pakai_default_tapi_alamat_default_belum_lengkap_tetap_wajib_isi_alamat(): void
    {
        $c = $this->customer(); // Belum punya alamat

        $this->withSession(['customer_id' => $c->id])->post('/reservasi', [
            'use_default_address' => '1',
            'service' => 'Cuci Kering Lipat', 'pickup_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('address');
    }

    // ---------- Menu Edit Alamat ----------

    public function test_edit_alamat_wajib_masuk(): void
    {
        $this->get('/akun/alamat')
            ->assertRedirect(route('login.form'))
            ->assertSessionHas('after_login', route('address.edit'));
        $this->put('/akun/alamat', ['address' => 'Jl. Apa Saja No. 1', 'maps_url' => 'https://maps.app.goo.gl/x'])
            ->assertRedirect(route('login.form'));
    }

    public function test_edit_alamat_menampilkan_alamat_sekarang(): void
    {
        $c = $this->customer(['address' => 'Jl. Kenanga No. 7, Bekasi', 'maps_url' => 'https://maps.app.goo.gl/rumah']);

        $this->withSession(['customer_id' => $c->id])->get('/akun/alamat')
            ->assertOk()
            ->assertSee('Edit alamat')
            ->assertSee('Jl. Kenanga No. 7, Bekasi')
            ->assertSee('value="https://maps.app.goo.gl/rumah"', false);
    }

    public function test_edit_alamat_lokasi_wajib_diisi(): void
    {
        $c = $this->customer();

        $this->withSession(['customer_id' => $c->id])
            ->put('/akun/alamat', ['address' => 'Jl. Mawar No. 9, RT 01/RW 02', 'maps_url' => ''])
            ->assertSessionHasErrors('maps_url');
        $this->assertNull($c->fresh()->address);
    }

    public function test_edit_alamat_menyimpan_alamat_default(): void
    {
        $c = $this->customer(['address' => 'Alamat lama banget', 'maps_url' => 'https://maps.app.goo.gl/lama']);

        $this->withSession(['customer_id' => $c->id])
            ->put('/akun/alamat', ['address' => 'Jl. Mawar No. 9, RT 01/RW 02', 'maps_url' => 'https://www.google.com/maps?q=-6.2,106.9'])
            ->assertRedirect(route('address.edit'))
            ->assertSessionHas('status');

        $c->refresh();
        $this->assertSame('Jl. Mawar No. 9, RT 01/RW 02', $c->address);
        $this->assertSame('https://www.google.com/maps?q=-6.2,106.9', $c->maps_url);
    }

    public function test_edit_alamat_dari_reservasi_kembali_ke_form_reservasi(): void
    {
        $c = $this->customer();

        $this->withSession(['customer_id' => $c->id])->get('/akun/alamat?next=reservasi')
            ->assertSee('name="next" value="reservasi"', false)
            ->assertSee('Kembali ke reservasi');

        $this->withSession(['customer_id' => $c->id])
            ->put('/akun/alamat', ['address' => 'Jl. Mawar No. 9, RT 01/RW 02', 'maps_url' => 'https://maps.app.goo.gl/baru', 'next' => 'reservasi'])
            ->assertRedirect(route('customer.home') . '#pesan');
    }

    public function test_edit_alamat_next_asing_diabaikan(): void
    {
        $c = $this->customer();

        $this->withSession(['customer_id' => $c->id])->get('/akun/alamat?next=reservasi')
            ->assertSee('name="next" value="reservasi"', false);
        $this->withSession(['customer_id' => $c->id])->get('/akun/alamat?next=https://situs-jahat.test')
            ->assertDontSee('name="next"', false);
    }

    public function test_reservasi_dengan_perkiraan_berat_menghitung_perkiraan_harga(): void
    {
        \Illuminate\Support\Facades\DB::table('service_categories')->insert(['id' => 1, 'name' => 'Kiloan']);
        \App\Models\Service::create([
            'category_id' => 1, 'name' => 'Cuci Kering Setrika', 'unit' => 'kg',
            'price' => 8000, 'duration_hours' => 48, 'is_active' => 1,
        ]);
        $c = $this->customer();

        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', $this->validData([
            'estimated_weight' => 3.5,
        ]));

        $text = rawurldecode($res->headers->get('Location'));
        $this->assertStringContainsString('Perkiraan berat: 3,5 kg', $text);
        $this->assertStringContainsString('Perkiraan harga: Rp28.000', $text);

        $r = \App\Models\Reservation::first();
        $this->assertSame('3,5 kg', $r->estimated_weight);
        $this->assertSame('Rp28.000', $r->estimated_price);
    }
}
