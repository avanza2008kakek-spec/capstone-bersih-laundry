<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test tidak boleh menghubungi internet sungguhan (Google Maps, Hub WA, peta): wajib lewat Http::fake
        Http::preventStrayRequests();
    }
}
