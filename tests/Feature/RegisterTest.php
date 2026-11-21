<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HubRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji alur pendaftaran pelanggan dengan kode verifikasi WhatsApp.
 * POSAJA Verification Hub disimulasikan (Http::fake), jadi tidak ada WhatsApp sungguhan yang terkirim.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sentCode = null;                           // Kode yang "dikirim" ke Hub tiruan
    private ?array $hubReply = null;                            // Jika diisi, Hub tiruan menjawab dengan ini (cth: gagal)
    private ?string $sentCallback = null;                       // callback_url yang dikirim ke Hub tiruan (tujuan link di pesan WA)
    private bool $sentPesan = false;                            // Apakah Laundry ikut mengirim isi pesan sendiri (seharusnya tidak)

    /** Link verifikasi otomatis = callback_url terakhir yang dikirim ke Hub (Hub memasangnya di pesan WA). */
    private function magicLink(): string
    {
        return (string) $this->sentCallback;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');                                  // File pengaturan (nama laundry) ditulis ke folder tiruan

        // Tabel customers dibuat lewat SQL di hosting (tanpa migrasi), jadi dibuat manual untuk test
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('phone')->nullable();
                $t->text('address')->nullable();
                $t->timestamp('phone_verified_at')->nullable();
                $t->timestamps();
            });
        }

        // Hub tiruan: catat kode yang dikirim, jawab sukses seperti dokumentasi Hub
        Http::fake(function (HubRequest $request) {
            if ($this->hubReply) return Http::response($this->hubReply);
            $this->sentCode = $request['kode_otp'];
            $this->sentCallback = $request['callback_url'];
            $this->sentPesan = isset($request['pesan']);
            return Http::response(['success' => true, 'request_id' => 'REQ-1', 'kode_otp' => $request['kode_otp']]);
        });
    }

    public function test_daftar_mengirim_kode_ke_hub_dengan_data_yang_benar(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '0812-3456-7890'])
            ->assertRedirect(route('register.verify'));

        Http::assertSent(fn (HubRequest $r) =>
            $r->url() === config('services.verification_hub.url')
            && $r['nomor_wa'] === '081234567890'                // Dikirim format lokal
            && $r['app_name'] === 'Bersih Laundry'              // Nama bawaan dari Pengaturan Usaha
            && preg_match('/^\d{6}$/', $r['kode_otp']));

        $v = PhoneVerification::first();
        $this->assertSame('6281234567890', $v->phone);          // Disimpan format 628
        $this->assertSame('REQ-1', $v->hub_request_id);
        $this->assertNotSame($this->sentCode, $v->code_hash);   // Kode asli tidak disimpan
    }

    public function test_kode_benar_membuat_pelanggan_terverifikasi(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);

        $this->post('/daftar/verifikasi', ['code' => $this->sentCode])
            ->assertRedirect(route('register.done'));

        $customer = Customer::first();
        $this->assertSame('Budi', $customer->name);
        $this->assertSame('081234567890', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);
        $this->get('/daftar/berhasil')->assertOk()->assertSee('Halo, Budi!');
    }

    public function test_pelanggan_lama_dari_kasir_tidak_dobel(): void
    {
        Customer::create(['name' => 'Bu Siti (kasir)', 'phone' => '081234567890']);

        $this->post('/daftar', ['name' => 'Siti', 'phone' => '+62 812 3456 7890']);
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode]);

        $this->assertSame(1, Customer::count());
        $this->assertSame('Bu Siti (kasir)', Customer::first()->name); // Nama dari kasir tidak ditimpa
        $this->assertNotNull(Customer::first()->phone_verified_at);
    }

    public function test_kode_salah_dihitung_dan_hangus_setelah_5_kali(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $wrong = $this->sentCode === '000000' ? '111111' : '000000';

        $this->post('/daftar/verifikasi', ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertSame(1, PhoneVerification::first()->attempts);

        for ($i = 0; $i < 4; $i++) $this->post('/daftar/verifikasi', ['code' => $wrong]);

        // Setelah 5x salah, kode yang benar pun ditolak
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode])->assertSessionHasErrors('code');
        $this->assertSame(0, Customer::count());
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $this->travel(11)->minutes();                            // Lewat batas 10 menit

        $this->post('/daftar/verifikasi', ['code' => $this->sentCode])->assertSessionHasErrors('code');
        $this->assertSame(0, Customer::count());
    }

    public function test_minta_kode_ulang_harus_tunggu_60_detik(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);

        $this->post('/daftar/kirim-ulang')->assertSessionHasErrors('code');
        Http::assertSentCount(1);                                // Hub tidak dipanggil lagi

        $this->travel(61)->seconds();
        $this->post('/daftar/kirim-ulang')->assertRedirect(route('register.verify'));
        Http::assertSentCount(2);
        $this->assertSame(2, PhoneVerification::count());
    }

    public function test_nomor_tidak_valid_ditolak_tanpa_memanggil_hub(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '12345'])->assertSessionHasErrors('phone');
        Http::assertNothingSent();
    }

    public function test_hub_gagal_menampilkan_pesan_dan_tidak_menyimpan_kode(): void
    {
        $this->hubReply = ['success' => false, 'error' => 'Kuota habis.'];

        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890'])
            ->assertSessionHasErrors(['phone' => 'Kuota habis. Silakan coba lagi.']);
        $this->assertSame(0, PhoneVerification::count());
    }

    public function test_kirim_ulang_lewat_get_diarahkan_ke_verifikasi_tanpa_kirim_kode(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);

        $this->get('/daftar/kirim-ulang')->assertRedirect(route('register.verify')); // Bukan error 405
        Http::assertSentCount(1);                                // GET tidak memicu kirim kode baru
    }

    // ---------- Cek nomor saat Daftar ----------

    public function test_daftar_nomor_yang_sudah_terverifikasi_ditolak_dan_diarahkan_ke_masuk(): void
    {
        Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);

        $this->from('/daftar')->post('/daftar', ['name' => 'Budi Lagi', 'phone' => '+62 812-3456-7890'])
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors(['phone' => 'Nomor ini sudah terdaftar. Silakan masuk.'])
            ->assertSessionHas('registered', true);
        Http::assertNothingSent();                               // Tidak ada kode yang dikirim

        // Nomor langsung terisi saat pindah ke tab Masuk
        $this->get('/daftar');                                   // Halaman Daftar tampil (old input dipakai di sini)
        $this->get('/masuk')->assertSee('value="081234567890"', false);
    }

    // ---------- Masuk ----------

    public function test_masuk_dengan_nomor_belum_terdaftar_ditolak_dan_diarahkan_ke_daftar(): void
    {
        $this->from('/masuk')->post('/masuk', ['phone' => '081234567890'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors(['phone' => 'Nomor ini belum terdaftar. Silakan daftar dulu.'])
            ->assertSessionHas('unregistered', true);
        Http::assertNothingSent();
    }

    public function test_masuk_nomor_terdaftar_kirim_kode_login_lalu_berhasil_masuk(): void
    {
        $customer = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()->subDay()]);

        $this->post('/masuk', ['phone' => '081234567890'])->assertRedirect(route('register.verify'));
        Http::assertSent(fn (HubRequest $r) => $r['keperluan'] === 'Login Pelanggan Laundry');

        $this->get('/daftar/verifikasi')->assertSee('Verifikasi &amp; masuk', false);
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode])->assertRedirect(route('register.done'));

        $this->assertSame(1, Customer::count());                 // Tidak membuat pelanggan baru
        $this->assertSame($customer->id, session('customer_id'));
        $this->get('/daftar/berhasil')->assertSee('Selamat datang kembali, Budi!');
    }

    public function test_masuk_pelanggan_dari_kasir_yang_belum_verifikasi_boleh_dan_jadi_terverifikasi(): void
    {
        Customer::create(['name' => 'Bu Siti', 'phone' => '6281234567890']); // Dicatat kasir format 628, belum verifikasi

        $this->post('/masuk', ['phone' => '0812 3456 7890'])->assertRedirect(route('register.verify'));
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode]);

        $this->assertNotNull(Customer::first()->phone_verified_at);
    }

    public function test_keluar_menghapus_status_masuk(): void
    {
        $customer = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);

        $this->withSession(['customer_id' => $customer->id])
            ->post('/keluar')->assertRedirect(route('customer.home'));
        $this->assertNull(session('customer_id'));
        $this->get('/daftar/berhasil')->assertRedirect(route('register.create'));
    }

    // ---------- Isi kode otomatis dari link di pesan WA ----------

    public function test_hub_menerima_callback_url_dan_pesan_wa_dibuat_oleh_hub(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);

        $this->assertFalse($this->sentPesan);                    // Isi pesan WA diserahkan ke Hub
        $this->assertStringContainsString('/daftar/v/', $this->magicLink());
        $this->assertStringContainsString('kode=' . $this->sentCode, $this->magicLink());
        $this->assertStringContainsString('signature=', $this->magicLink());
        $this->assertStringContainsString('expires=', $this->magicLink());
    }

    public function test_link_wa_di_browser_lain_mengisi_kode_otomatis_lalu_berhasil(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $link = $this->magicLink();
        $code = $this->sentCode;

        // Dibuka di browser lain (misal browser bawaan WhatsApp): sesi baru yang kosong
        $this->flushSession();
        $this->get($link)
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('autofill_code', $code);

        // Halaman kode: kotak sudah terisi & ditandai untuk dikirim otomatis
        $this->get('/daftar/verifikasi')
            ->assertSee('value="' . $code . '"', false)
            ->assertSee('data-autofill="1"', false)
            ->assertSee('sedang memverifikasi');

        // JavaScript halaman lalu mengirim form → berhasil daftar
        $this->post('/daftar/verifikasi', ['code' => $code])->assertRedirect(route('register.done'));
        $this->assertNotNull(Customer::first()?->phone_verified_at);
    }

    public function test_link_wa_untuk_masuk_menampilkan_mode_masuk(): void
    {
        Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);
        $this->post('/masuk', ['phone' => '081234567890']);

        $this->flushSession();
        $this->get($this->magicLink());
        $this->get('/daftar/verifikasi')->assertSee('Verifikasi &amp; masuk', false);
    }

    public function test_link_wa_yang_diubah_ditolak(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $tampered = str_replace('kode=' . $this->sentCode, 'kode=000000', $this->magicLink());

        $this->flushSession();
        $this->get($tampered)
            ->assertRedirect(route('register.create'))
            ->assertSessionHasErrors('phone')
            ->assertSessionMissing('register.verification_id');
    }

    public function test_link_wa_kedaluwarsa_ditolak(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $this->travel(11)->minutes();                            // Lewat batas 10 menit

        $this->get($this->magicLink())->assertSessionHasErrors(['phone' => 'Link verifikasi sudah tidak berlaku. Silakan minta kode baru.']);
    }

    public function test_link_wa_tidak_bisa_dipakai_ulang_setelah_verifikasi(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);
        $link = $this->magicLink();
        $this->post('/daftar/verifikasi', ['code' => $this->sentCode]);

        $this->flushSession();
        $this->get($link)->assertSessionHasErrors('phone')->assertSessionMissing('register.verification_id');
    }

    public function test_halaman_kode_biasa_tidak_mengirim_otomatis(): void
    {
        $this->post('/daftar', ['name' => 'Budi', 'phone' => '081234567890']);

        $this->get('/daftar/verifikasi')
            ->assertSee('data-autofill="0"', false)
            ->assertSee('Tempel kode dari WhatsApp');
    }
}
