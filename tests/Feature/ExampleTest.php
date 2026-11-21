<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLaundryTables;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase, CreatesLaundryTables;

    /**
     * Halaman depan bisa dibuka (tabel laundry dibuat dulu karena di hosting dibuat lewat SQL, bukan migrasi).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        Storage::fake('local');
        $this->createLaundryTables();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
