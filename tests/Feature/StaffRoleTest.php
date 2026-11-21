<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Uji pembagian menu per role (owner/kasir/operator), Kelola Staf, dan halaman atur password owner. */
class StaffRoleTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();                            // Dashboard membaca tabel orders, payments, expenses
    }

    private function user(string $role, string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Satu', 'email' => $email ?? "{$role}@laundry.test",
            'password' => 'rahasia123', 'role' => $role,
        ]);
    }

    private function staffData(array $override = []): array
    {
        return array_merge([
            'name' => 'Sari Kasir', 'email' => 'sari@laundry.test', 'phone' => '081211112231', 'role' => 'kasir',
            'password' => 'kasirBaru123', 'password_confirmation' => 'kasirBaru123',
        ], $override);
    }

    // ---------- Menu & hak akses per role ----------

    public function test_menu_owner_lengkap(): void
    {
        $this->actingAs($this->user('owner'))->get('/kasir')
            ->assertOk()
            ->assertSee('Kelola Staf')->assertSee('Laporan Keuangan')->assertSee('Pengaturan Usaha')
            ->assertSee('Transaksi')->assertSee('Antrean Cucian');
    }

    public function test_menu_kasir_tanpa_keuangan_dan_staf(): void
    {
        $this->actingAs($this->user('kasir'))->get('/kasir')
            ->assertOk()
            ->assertSee('Transaksi')->assertSee('Pelanggan')->assertSee('Antrean Cucian')
            ->assertDontSee('Kelola Staf')->assertDontSee('Laporan Keuangan')->assertDontSee('Pengaturan Usaha');
    }

    public function test_menu_operator_hanya_antrean(): void
    {
        $this->actingAs($this->user('operator'))->get('/kasir')
            ->assertOk()
            ->assertSee('Antrean Cucian')
            ->assertDontSee('Transaksi')->assertDontSee('Pengeluaran')->assertDontSee('Kelola Staf')->assertDontSee('Laporan Keuangan');
    }

    public function test_halaman_owner_dikunci_untuk_kasir_dan_operator(): void
    {
        foreach (['kasir', 'operator'] as $role) {
            $this->actingAs($this->user($role));
            $this->get('/kasir/staf')->assertForbidden();
            $this->get('/kasir/pengaturan')->assertForbidden();
            $this->get('/laporan')->assertForbidden();
            $this->post('/kasir/staf', $this->staffData(['role' => 'owner']))->assertForbidden(); // Tidak bisa membuat owner sendiri
        }
        $this->assertSame(0, User::where('email', 'sari@laundry.test')->count());
    }

    public function test_laporan_keuangan_wajib_login(): void
    {
        $this->get('/laporan')->assertRedirect(route('kasir.login'));
    }

    // ---------- Kelola Staf (owner) ----------

    public function test_owner_membuat_akun_kasir_dan_akun_bisa_login(): void
    {
        $this->actingAs($this->user('owner'));

        $this->post('/kasir/staf', $this->staffData())
            ->assertRedirect(route('kasir.staff.index'))
            ->assertSessionHas('status');

        $sari = User::where('email', 'sari@laundry.test')->first();
        $this->assertSame('kasir', $sari->role);
        $this->assertSame('081211112231', $sari->phone);
        $this->assertTrue(Hash::check('kasirBaru123', $sari->password));     // Tersimpan sebagai hash

        // Akun baru bisa login & melihat menu kasir
        $this->post('/kasir/keluar');
        $this->post('/kasir/masuk', ['email' => 'sari@laundry.test', 'password' => 'kasirBaru123'])->assertRedirect(route('kasir.home'));
        $this->get('/kasir')->assertSee('Anda masuk sebagai kasir');
    }

    public function test_owner_mengubah_role_kasir_menjadi_operator(): void
    {
        $this->actingAs($this->user('owner'));
        $kasir = $this->user('kasir');

        $this->put("/kasir/staf/{$kasir->id}", $this->staffData([
            'name' => $kasir->name, 'email' => $kasir->email, 'role' => 'operator', 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect(route('kasir.staff.index'));

        $kasir->refresh();
        $this->assertSame('operator', $kasir->role);
        $this->assertTrue(Hash::check('rahasia123', $kasir->password));     // Password kosong = tidak diganti
    }

    public function test_owner_mengganti_password_staf(): void
    {
        $this->actingAs($this->user('owner'));
        $op = $this->user('operator');

        $this->put("/kasir/staf/{$op->id}", $this->staffData([
            'name' => $op->name, 'email' => $op->email, 'role' => 'operator',
            'password' => 'passBaru999', 'password_confirmation' => 'passBaru999',
        ]));
        $this->assertTrue(Hash::check('passBaru999', $op->fresh()->password));
    }

    public function test_validasi_email_unik_role_valid_password_kuat(): void
    {
        $this->actingAs($this->user('owner'));
        $this->user('kasir', 'sari@laundry.test');

        $this->post('/kasir/staf', $this->staffData())->assertSessionHasErrors('email');                       // Email sudah dipakai
        $this->post('/kasir/staf', $this->staffData(['email' => 'x@laundry.test', 'role' => 'admin']))->assertSessionHasErrors('role');
        $this->post('/kasir/staf', $this->staffData(['email' => 'y@laundry.test', 'password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors('password');
    }

    public function test_owner_tidak_bisa_menurunkan_role_dirinya_sendiri(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);

        $this->put("/kasir/staf/{$owner->id}", $this->staffData([
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'kasir', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors('role');
        $this->assertSame('owner', $owner->fresh()->role);
    }

    public function test_owner_menghapus_staf_tapi_tidak_bisa_menghapus_dirinya(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);
        $op = $this->user('operator');

        // Buat riwayat nota & pengeluaran atas nama staf yang akan dihapus
        $customer = \App\Models\Customer::create(['name' => 'Pelanggan', 'phone' => '081234567890']);
        $order = \App\Models\Order::create([
            'invoice_number' => 'INV-TEST-01', 'customer_id' => $customer->id, 'user_id' => $op->id,
            'order_date' => now(), 'estimate_date' => now()->addDay(), 'total_amount' => 10000,
            'discount' => 0, 'final_amount' => 10000, 'paid_amount' => 10000, 'payment_status' => 'paid', 'order_status' => 'received',
        ]);
        \App\Models\OrderStatusLog::create(['order_id' => $order->id, 'user_id' => $op->id, 'status' => 'received']);
        \App\Models\Payment::create([
            'order_id' => $order->id, 'user_id' => $op->id, 'payment_date' => now(),
            'amount' => 10000, 'payment_method' => 'cash', 'payment_type' => 'full_payment',
        ]);

        $this->delete("/kasir/staf/{$op->id}")->assertRedirect(route('kasir.staff.index'));
        $this->assertNull(User::find($op->id));
        $this->assertSame($owner->id, $order->fresh()->user_id);

        $this->delete("/kasir/staf/{$owner->id}")->assertSessionHasErrors('staff');
        $this->assertNotNull(User::find($owner->id));
    }

    public function test_daftar_staf_menampilkan_semua_akun_dan_role(): void
    {
        $this->actingAs($this->user('owner'));
        $this->user('kasir');
        $this->user('operator');

        $this->get('/kasir/staf')->assertOk()
            ->assertSee('kasir@laundry.test')->assertSee('operator@laundry.test')
            ->assertSee('Owner (pemilik)')->assertSee('Operator');
    }

    // ---------- Akun pertama & atur password owner ----------

    public function test_akun_pertama_menjadi_owner(): void
    {
        $this->post('/kasir/buat-akun', [
            'name' => 'Pemilik', 'email' => 'pemilik@laundry.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ]);
        $this->assertSame('owner', User::first()->role);
    }

    public function test_atur_owner_mati_jika_kunci_env_kosong(): void
    {
        config(['services.laundry.setup_key' => '']);
        $this->get('/kasir/atur-owner')->assertNotFound();
        $this->post('/kasir/atur-owner', [])->assertNotFound();
    }

    public function test_atur_password_owner_dengan_kunci_env(): void
    {
        config(['services.laundry.setup_key' => 'kunci-rahasia-panjang-123']);
        $owner = User::create(['name' => 'Budi', 'email' => 'budi.owner@laundry.test', 'password' => 'lama-tidak-diketahui1', 'role' => 'owner']);
        $kasir = $this->user('kasir');

        $this->get('/kasir/atur-owner')->assertOk()->assertSee('budi.owner@laundry.test')->assertDontSee($kasir->email);

        // Kunci salah → ditolak
        $this->post('/kasir/atur-owner', ['setup_key' => 'salah', 'email' => $owner->email, 'password' => 'ownerBaru123', 'password_confirmation' => 'ownerBaru123'])
            ->assertSessionHasErrors('setup_key');
        // Akun bukan owner → ditolak
        $this->post('/kasir/atur-owner', ['setup_key' => 'kunci-rahasia-panjang-123', 'email' => $kasir->email, 'password' => 'ownerBaru123', 'password_confirmation' => 'ownerBaru123'])
            ->assertSessionHasErrors('email');

        // Benar → password diganti & langsung masuk
        $this->post('/kasir/atur-owner', ['setup_key' => 'kunci-rahasia-panjang-123', 'email' => $owner->email, 'password' => 'ownerBaru123', 'password_confirmation' => 'ownerBaru123'])
            ->assertRedirect(route('kasir.home'));
        $this->assertTrue(Hash::check('ownerBaru123', $owner->fresh()->password));
        $this->assertAuthenticatedAs($owner);
    }
}
