@extends('layouts.public')

@section('title', 'Login Kasir - ' . $businessName)

@section('content')
<div class="auth-card">
    <span class="chip">Staff</span>
    <h2 class="mb-1">Login staf</h2>
    <p class="opacity-75 mb-4">Khusus kasir dan pemilik {{ $businessName }}.</p>

    {{-- Form login staf: email + password (bukan login pelanggan) --}}
    <form method="POST" action="{{ route('kasir.login.attempt') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-bold" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required
                   class="form-control @error('email') is-invalid @enderror">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required class="form-control">
        </div>
        <!-- Ingat saya: tetap login walau browser ditutup -->
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
        </div>
        <button type="submit" class="btn-pill btn-yellow w-100"><i class="bi bi-box-arrow-in-right"></i> Masuk</button>
    </form>
</div>
@endsection
