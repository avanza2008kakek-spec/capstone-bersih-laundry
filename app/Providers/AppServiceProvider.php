<?php

namespace App\Providers;

use App\Support\Settings;                                       // Pengaturan usaha (nama laundry, dll.)
use Illuminate\Support\Facades\View;                            // Untuk membagikan data ke semua view
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu objek Settings per request, supaya file pengaturan hanya dibaca sekali
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // $businessName tersedia di semua file Blade, cth: {{ $businessName }} → "Bersih Laundry"
        View::composer('*', function ($view) {
            $view->with('businessName', app(Settings::class)->businessName());
        });

        // Tombol halaman (1, 2, 3, ...) memakai gaya Bootstrap 5 yang sudah dimuat di layout
        \Illuminate\Pagination\Paginator::useBootstrapFive();
    }
}
