<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

/** Reservasi dari website tersimpan & muncul sebagai notifikasi di Area Kasir. */
class KasirReservationTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createLaundryTables();
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'phone_verified_at' => now(),
            'address' => 'Jl. Kenanga No. 7, Bekasi', 'maps_url' => 'https://www.google.com/maps?q=-6.2,106.9']);
    }

    private function staff(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => "$role@laundry.test", 'password' => 'rahasia123', 'role' => $role]);
    }

    public function test_reservasi_pelanggan_tersimpan_dan_tetap_ke_wa(): void
    {
        $c = $this->customer();
        $res = $this->withSession(['customer_id' => $c->id])->post('/reservasi', [
            'use_default_address' => 1, 'service' => 'Cuci Setrika', 'pickup_date' => now()->addDay()->toDateString(), 'notes' => 'Pagar hijau',
        ]);

        $this->assertStringStartsWith('https://wa.me/', $res->headers->get('Location'));
        $r = Reservation::first();
        $this->assertSame('new', $r->status);
        $this->assertSame($c->id, $r->customer_id);
        $this->assertSame('Cuci Setrika', $r->service);
        $this->assertSame('Jl. Kenanga No. 7, Bekasi', $r->address);
        $this->assertSame('Pagar hijau', $r->notes);
    }

    public function test_notifikasi_lencana_dashboard_dan_cek_json(): void
    {
        $c = $this->customer();
        Reservation::create(['customer_id' => $c->id, 'service' => 'Cuci Kering', 'pickup_date' => now()->addDay(), 'address' => $c->address]);
        $latest = Reservation::create(['customer_id' => $c->id, 'service' => 'Bedcover', 'pickup_date' => now()->addDays(2), 'address' => $c->address]);
        Reservation::create(['customer_id' => $c->id, 'service' => 'Lama', 'pickup_date' => now(), 'address' => $c->address, 'status' => 'picked_up']);

        // Operator juga diberi tahu (bisa jadi yang menjemput)
        $this->actingAs($this->staff('operator'));
        $this->get('/kasir')->assertOk()
            ->assertSee('2 reservasi baru')
            ->assertSee('<span class="resv-badge ">2</span>', false);

        $this->getJson('/kasir/reservasi/cek')->assertOk()->assertJson([
            'count' => 2, 'latest' => ['id' => $latest->id, 'name' => 'Budi', 'service' => 'Bedcover'],
        ]);

        // Tab "Perlu dijemput" hanya yang baru/dikonfirmasi
        $this->get('/kasir/reservasi')->assertOk()->assertSee('Bedcover')->assertSee('Cuci Kering')->assertDontSee('>Lama<', false);
        $this->get('/kasir/reservasi?status=picked_up')->assertOk()->assertSee('Lama');
    }

    public function test_ubah_status_reservasi(): void
    {
        $c = $this->customer();
        $r = Reservation::create(['customer_id' => $c->id, 'service' => 'Cuci', 'pickup_date' => now()->addDay(), 'address' => $c->address]);
        $kasir = $this->staff('kasir');
        $this->actingAs($kasir);

        $this->put("/kasir/reservasi/{$r->id}", ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame('confirmed', $r->status);
        $this->assertSame($kasir->id, $r->handled_by);
        $this->assertSame(0, Reservation::newCount());                      // Lencana hilang setelah dikonfirmasi

        $this->put("/kasir/reservasi/{$r->id}", ['status' => 'picked_up']);
        $this->get('/kasir/reservasi?status=picked_up')->assertSee('Buat Nota')->assertSee('pelanggan=' . $c->id, false);

        $this->put("/kasir/reservasi/{$r->id}", ['status' => 'hilang'])->assertSessionHasErrors('status');
    }

    public function test_tamu_tidak_bisa_melihat_reservasi(): void
    {
        $this->get('/kasir/reservasi')->assertRedirect();
        $this->getJson('/kasir/reservasi/cek')->assertUnauthorized();
    }
}
