<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Halaman yang wajib login staf (middleware "auth") → arahkan ke login kasir
        $middleware->redirectGuestsTo(fn () => route('kasir.login'));
        // Staf yang sudah login lalu membuka halaman login → arahkan ke dashboard kasir
        $middleware->redirectUsersTo(fn () => route('kasir.home'));
        // Nama pendek "role" untuk middleware penjaga role staf, cth: ->middleware('role:owner')
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
        // Catat otomatis aktivitas semua user (Owner, Kasir, Operator, Kurir, dan Pelanggan)
        $middleware->web(append: [\App\Http\Middleware\LogUserActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
