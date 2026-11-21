@extends('layouts.public')

@section('title', 'Daftar - ' . $businessName)

@section('content')
<div class="auth-card">
    @include('register._tabs', ['active' => 'daftar'])
    <h2 class="mb-1">Daftar pakai nomor WhatsApp</h2>
    <p class="opacity-75 mb-4">Kami kirim kode verifikasi 6 digit ke WhatsApp Anda.</p>

    {{-- Form dikirim (POST) ke /daftar; @@csrf = token keamanan wajib Laravel --}}
    <form method="POST" action="{{ route('register.store') }}" id="form-daftar">
        @csrf

        <div class="mb-3">
            <label class="form-label fw-bold" for="name">Nama</label>
            <!-- old('name'): isi kembali input jika form ditolak karena ada kesalahan -->
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" autocomplete="name" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold" for="phone">Nomor WhatsApp</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                <!-- inputmode="tel": keyboard angka muncul di HP -->
                <!-- prefill_phone: nomor yang tadi diketik di tab Masuk (jika diarahkan ke sini karena belum terdaftar) -->
                <input type="tel" id="phone" name="phone" value="{{ old('phone') ?? session()->pull('prefill_phone') }}" inputmode="tel"
                       placeholder="081234567890" autocomplete="tel"
                       class="form-control @error('phone') is-invalid @enderror" required>
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <!-- Nomor sudah terdaftar: tampilkan tombol pindah ke tab Masuk -->
            @if(session('registered'))
                <a href="{{ route('login.form') }}" class="btn-pill btn-white w-100 mt-2 py-1">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk dengan nomor ini
                </a>
            @else
                <small class="opacity-75">Pastikan nomor ini aktif dan terpasang WhatsApp.</small>
            @endif
        </div>

        <button type="submit" class="btn-pill btn-yellow w-100" id="btn-daftar">
            <i class="bi bi-send-fill"></i> Daftar &amp; kirim kode
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Cegah klik dobel: tombol dinonaktifkan setelah form dikirim
    document.getElementById('form-daftar').addEventListener('submit', function () {
        const btn = document.getElementById('btn-daftar');
        btn.disabled = true;
        btn.innerHTML = 'Memproses...';
    });
</script>
@endpush
