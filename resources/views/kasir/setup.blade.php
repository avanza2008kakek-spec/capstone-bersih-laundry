@extends('layouts.public')

@section('title', 'Buat Akun Kasir - ' . $businessName)

@section('content')
<div class="auth-card">
    <span class="chip">Langkah pertama</span>
    <h2 class="mb-1">Buat akun kasir</h2>
    <p class="opacity-75 mb-4">
        Belum ada akun staf. Akun yang dibuat di sini menjadi akun pertama untuk masuk ke Staff.
        Halaman ini otomatis tertutup setelah akun dibuat.
    </p>

    {{-- Form akun pertama: hanya bisa dipakai selama tabel users masih kosong --}}
    <form method="POST" action="{{ route('kasir.setup.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-bold" for="name">Nama</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required
                   class="form-control @error('email') is-invalid @enderror">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required
                   class="form-control @error('password') is-invalid @enderror">
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="opacity-75">Minimal 8 karakter, berisi huruf dan angka.</small>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold" for="password_confirmation">Ulangi password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required class="form-control">
        </div>
        <button type="submit" class="btn-pill btn-yellow w-100"><i class="bi bi-person-check-fill"></i> Buat akun &amp; masuk</button>
    </form>
</div>
@endsection
