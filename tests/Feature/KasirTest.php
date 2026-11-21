<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HubRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji Area Kasir: login staf, akun pertama, dan menu Pengaturan Usaha (ganti nama laundry). */
class KasirTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');                                  // File settings.json ditulis ke folder tiruan
        $this->createLaundryTables();                            // Dashboard membaca tabel orders, payments, expenses
    }

    // Akun owner (Pengaturan Usaha khusus owner)
    private function kasir(): User
    {
        return User::create(['name' => 'Rina', 'email' => 'rina@laundry.test', 'password' => 'rahasia123', 'role' => User::OWNER]);
    }

    public function test_nama_bawaan_bersih_laundry(): void
    {
        $this->assertSame('Bersih Laundry', app(Settings::class)->businessName());
        $this->get('/daftar')->assertSee('<title>Daftar - Bersih Laundry</title>', false);
    }

    public function test_tamu_tidak_bisa_membuka_area_kasir(): void
    {
        $this->kasir();
        $this->get('/kasir')->assertRedirect(route('kasir.login'));
        $this->get('/kasir/pengaturan')->assertRedirect(route('kasir.login'));
        $this->put('/kasir/pengaturan', ['business_name' => 'Laundry Jahil'])->assertRedirect(route('kasir.login'));
        $this->assertSame('Bersih Laundry', app(Settings::class)->businessName()); // Nama tidak berubah
    }

    public function test_akun_pertama_hanya_bisa_dibuat_saat_belum_ada_akun(): void
    {
        $this->get('/kasir/masuk')->assertRedirect(route('kasir.setup'));  // Belum ada akun → halaman buat akun

        $this->post('/kasir/buat-akun', [
            'name' => 'Pemilik', 'email' => 'owner@laundry.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('kasir.home'));
        $this->assertAuthenticated();

        // Setelah ada akun, halaman buat akun tertutup (walau sudah keluar)
        $this->post('/kasir/keluar');
        $this->get('/kasir/buat-akun')->assertNotFound();
        $this->post('/kasir/buat-akun', [
            'name' => 'Penyusup', 'email' => 'x@x.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertNotFound();
        $this->assertSame(1, User::count());
    }

    public function test_login_kasir_salah_dan_benar(): void
    {
        $this->kasir();

        $this->post('/kasir/masuk', ['email' => 'rina@laundry.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/kasir/masuk', ['email' => 'rina@laundry.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('kasir.home'));
        $this->assertAuthenticated();
        $this->get('/kasir')->assertOk()->assertSee('Halo, Rina!');
    }

    public function test_kasir_mengganti_nama_laundry_dan_dipakai_di_halaman_serta_pesan_wa(): void
    {
        $this->actingAs($this->kasir());

        $this->get('/kasir/pengaturan')->assertOk()->assertSee('value="Bersih Laundry"', false);

        $this->put('/kasir/pengaturan', ['business_name' => '  Laundry Wangi  '])
            ->assertRedirect(route('kasir.settings'))
            ->assertSessionHas('status');

        // Tersimpan di file & langsung dipakai di halaman pelanggan
        $this->assertSame('Laundry Wangi', json_decode(Storage::disk('local')->get('settings.json'), true)['business_name']);
        $this->app->forgetInstance(Settings::class);             // Seperti request baru: file dibaca ulang
        $this->get('/masuk')->assertSee('<title>Masuk - Laundry Wangi</title>', false);

        // Nama baru ikut dikirim ke Verification Hub (muncul di pesan WA)
        Http::fake(['*' => Http::response(['success' => true, 'request_id' => 'R1', 'kode_otp' => '123456'])]);
        app(\App\Services\VerificationHub::class)->send('081234567890', '123456');
        Http::assertSent(fn (HubRequest $r) => $r['app_name'] === 'Laundry Wangi');
    }

    public function test_nama_laundry_wajib_diisi_dan_maksimal_40_huruf(): void
    {
        $this->actingAs($this->kasir());

        $this->put('/kasir/pengaturan', ['business_name' => ''])->assertSessionHasErrors('business_name');
        $this->put('/kasir/pengaturan', ['business_name' => str_repeat('A', 41)])->assertSessionHasErrors('business_name');
        $this->assertSame('Bersih Laundry', app(Settings::class)->businessName());
    }
}
