<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\ReverseGeocoder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji tombol "Gunakan lokasi saya": titik GPS → alamat teks (OpenStreetMap Nominatim disimulasikan).
 */
class AddressFromLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('phone')->nullable(); $t->text('address')->nullable();
            $t->string('maps_url', 500)->nullable(); $t->timestamp('phone_verified_at')->nullable(); $t->timestamps();
        });
    }

    private function login(): Customer
    {
        $c = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now()]);
        $this->withSession(['customer_id' => $c->id]);
        return $c;
    }

    // Jawaban tiruan Nominatim untuk sebuah titik di Bekasi
    private function fakeNominatim(array $address): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => $address])]);
    }

    public function test_alamat_lengkap_dari_lokasi(): void
    {
        $this->login();
        $this->fakeNominatim([
            'road' => 'Jalan Melati', 'house_number' => '5', 'neighbourhood' => 'RT 02/RW 03',
            'village' => 'Jatibening', 'city_district' => 'Pondok Gede', 'city' => 'Kota Bekasi', 'postcode' => '17412',
        ]);

        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.2383&lng=106.9756')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'address' => 'Jalan Melati No. 5, RT 02/RW 03, Kel. Jatibening, Kec. Pondok Gede, Kota Bekasi, 17412',
                'missing' => [],
            ]);

        // Aturan Nominatim: nama aplikasi di User-Agent & bahasa Indonesia
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->header('User-Agent')[0], 'http')
            && $r->header('Accept-Language')[0] === 'id' && $r['zoom'] == 18);
    }

    public function test_nomor_rumah_dan_rt_rw_tidak_ada_diberi_penanda_isi(): void
    {
        $this->login();
        $this->fakeNominatim(['road' => 'Jalan Kenanga', 'village' => 'Jatibening', 'city' => 'Kota Bekasi']);

        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.2&lng=106.9')
            ->assertJson([
                'address' => 'Jalan Kenanga No. (isi), RT (isi)/RW (isi), Kel. Jatibening, Kota Bekasi',
                'missing' => ['nomor rumah', 'RT/RW'],
            ]);
    }

    public function test_hasil_disimpan_cache_tidak_bertanya_ulang(): void
    {
        $this->login();
        $this->fakeNominatim(['road' => 'Jalan Kenanga', 'house_number' => '7']);

        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.20001&lng=106.90001');
        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.20002&lng=106.90002'); // Titik sangat berdekatan
        Http::assertSentCount(1);
    }

    public function test_layanan_peta_gagal_memberi_pesan_ketik_manual(): void
    {
        $this->login();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('error', 500)]);

        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.2&lng=106.9')
            ->assertJson(['ok' => false])
            ->assertJsonPath('error', 'Alamat dari lokasi belum bisa diambil. Silakan ketik alamat secara manual.');
    }

    public function test_wajib_masuk_dan_koordinat_harus_valid(): void
    {
        Http::fake();
        $this->getJson('/akun/alamat/dari-lokasi?lat=-6.2&lng=106.9')->assertStatus(401);

        $this->login();
        $this->getJson('/akun/alamat/dari-lokasi?lat=999&lng=abc')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_alamat_yang_masih_berisi_penanda_isi_ditolak(): void
    {
        $c = $this->login();

        $this->put('/akun/alamat', [
            'address'  => 'Jalan Kenanga No. (isi), RT (isi)/RW (isi), Kota Bekasi',
            'maps_url' => 'https://www.google.com/maps?q=-6.2,106.9',
        ])->assertSessionHasErrors(['address' => 'Lengkapi dulu bagian alamat yang bertanda (isi), misalnya nomor rumah dan RT/RW.']);
        $this->assertNull($c->fresh()->address);

        // Setelah dilengkapi → diterima
        $this->put('/akun/alamat', [
            'address'  => 'Jalan Kenanga No. 7, RT 01/RW 02, Kota Bekasi',
            'maps_url' => 'https://www.google.com/maps?q=-6.2,106.9',
        ])->assertSessionHasNoErrors();
    }

    public function test_halaman_edit_alamat_memuat_keterangan_cek_kembali(): void
    {
        $this->login();
        $this->get('/akun/alamat')
            ->assertSee('id="alamat-cek"', false)
            ->assertSee('akun\/alamat\/dari-lokasi', false);    // Alamat endpoint di JavaScript (garis miring ditulis \/ oleh json)
    }

    // ---------- Menu "Pilih di peta" ----------

    public function test_halaman_edit_alamat_punya_menu_pilih_di_peta(): void
    {
        $this->login();
        $this->get('/akun/alamat')
            ->assertSee('Pilih di peta')
            ->assertSee('id="picker-map"', false)
            ->assertSee('Pakai titik ini')
            ->assertSee('akun\/alamat\/cari-tempat', false);
    }

    public function test_cari_tempat_mengembalikan_daftar_hasil_dengan_koordinat(): void
    {
        $this->login();
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([
            ['display_name' => 'Jalan Lapangan Serbaguna, Margahayu, Bekasi Timur, Bekasi', 'lat' => '-6.24963', 'lon' => '107.010922'],
            ['display_name' => '', 'lat' => '0', 'lon' => '0'],  // Hasil tanpa nama dibuang
        ])]);

        $this->getJson('/akun/alamat/cari-tempat?q=serbaguna margahayu')
            ->assertOk()
            ->assertJson(['ok' => true, 'results' => [
                ['label' => 'Jalan Lapangan Serbaguna, Margahayu, Bekasi Timur, Bekasi', 'lat' => -6.24963, 'lng' => 107.010922],
            ]])
            ->assertJsonCount(1, 'results');

        // Hanya mencari di Indonesia, maksimal 5 hasil
        Http::assertSent(fn (HttpRequest $r) => $r['countrycodes'] === 'id' && $r['limit'] == 5 && $r['q'] === 'serbaguna margahayu');
    }

    public function test_cari_tempat_disimpan_cache(): void
    {
        $this->login();
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([])]);

        $this->getJson('/akun/alamat/cari-tempat?q=Margahayu');
        $this->getJson('/akun/alamat/cari-tempat?q=margahayu');  // Huruf besar/kecil dianggap sama
        Http::assertSentCount(1);
    }

    public function test_cari_tempat_wajib_masuk_dan_minimal_3_huruf(): void
    {
        Http::fake();
        $this->getJson('/akun/alamat/cari-tempat?q=bekasi')->assertStatus(401);

        $this->login();
        $this->getJson('/akun/alamat/cari-tempat?q=ab')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_cari_tempat_gagal_memberi_pesan(): void
    {
        $this->login();
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response('error', 503)]);

        $this->getJson('/akun/alamat/cari-tempat?q=bekasi')
            ->assertJson(['ok' => false, 'results' => []])
            ->assertJsonPath('error', 'Pencarian sedang tidak bisa dipakai. Geser peta secara manual.');
    }
}
