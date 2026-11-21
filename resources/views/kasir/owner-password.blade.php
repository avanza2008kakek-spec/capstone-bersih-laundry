@extends('layouts.public')

@section('title', 'Atur Password Owner - ' . $businessName)

@section('content')
<div class="auth-card">
    <span class="chip">Pengaturan awal</span>
    <h2 class="mb-1">Atur password owner</h2>
    <p class="opacity-75 mb-3">
        Halaman sementara untuk mengatur password akun owner. Masukkan <b>kunci pengaturan</b> yang sama dengan
        <code>KASIR_SETUP_KEY</code> di file <code>.env</code> server.
    </p>

    @if($owners->isEmpty())
        <div class="alert alert-warning">Belum ada akun ber-role <b>owner</b> di tabel users.</div>
    @endif

    {{-- Form atur password owner: kunci .env + email owner + password baru --}}
    <form method="POST" action="{{ route('kasir.owner-password.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-bold" for="setup_key">Kunci pengaturan</label>
            <input type="password" id="setup_key" name="setup_key" required autocomplete="off"
                   class="form-control @error('setup_key') is-invalid @enderror">
            @error('setup_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="email">Akun owner</label>
            <!-- Pilihan hanya akun yang ber-role owner -->
            <select id="email" name="email" required class="form-select form-control @error('email') is-invalid @enderror">
                @foreach($owners as $owner)
                    <option value="{{ $owner->email }}" @selected(old('email') === $owner->email)>{{ $owner->name }} ({{ $owner->email }})</option>
                @endforeach
            </select>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="password">Password baru</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="opacity-75">Minimal 8 karakter, berisi huruf dan angka.</small>
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold" for="password_confirmation">Ulangi password baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
        </div>
        <button type="submit" class="btn-pill btn-yellow w-100"><i class="bi bi-shield-lock-fill"></i> Simpan &amp; masuk</button>
    </form>
</div>
@endsection
