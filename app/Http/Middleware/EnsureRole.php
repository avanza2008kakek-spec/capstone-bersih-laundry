<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Penjaga halaman Area Kasir berdasarkan role staf.
 * Cara pakai di route: ->middleware('role:owner') atau ->middleware('role:owner,kasir')
 * Staf yang role-nya tidak termasuk akan melihat halaman "403 - Tidak punya akses".
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('kasir.login');           // Belum login staf
        }

        abort_unless($user->hasRole(...$roles), 403, 'Menu ini hanya untuk ' . implode(' / ', $roles) . '.');

        return $next($request);
    }
}
