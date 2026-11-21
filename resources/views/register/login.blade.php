@extends('layouts.public')

@section('title', 'Masuk - ' . $businessName)

@section('content')
<div class="auth-card">
    @include('register._tabs', ['active' => 'masuk'])
    <h2 class="mb-1">Masuk pakai nomor WhatsApp</h2>
    <p class="opacity-75 mb-4">Masukkan nomor yang sudah terdaftar. Kami kirim kode verifikasi 6 digit ke WhatsApp Anda.</p>

    {{-- Form dikirim (POST) ke /masuk; nomor dicek dulu apakah sudah terdaftar --}}
    <form method="POST" action="{{ route('login.send') }}" id="form-masuk">
        @csrf

        <div class="mb-4">
            <label class="form-label fw-bold" for="phone">Nomor WhatsApp</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                <!-- prefill_phone: nomor yang tadi diketik di tab Daftar (jika diarahkan ke sini karena sudah terdaftar) -->
                <input type="tel" id="phone" name="phone" value="{{ old('phone') ?? session()->pull('prefill_phone') }}" inputmode="tel"
                       placeholder="081234567890" autocomplete="tel" autofocus
                       class="form-control @error('phone') is-invalid @enderror" required>
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <!-- Nomor belum terdaftar: tampilkan tombol pindah ke tab Daftar -->
            @if(session('unregistered'))
                <a href="{{ route('register.create') }}" class="btn-pill btn-white w-100 mt-2 py-1">
                    <i class="bi bi-person-plus-fill"></i> Daftar dengan nomor ini
                </a>
            @endif
        </div>

        <button type="submit" class="btn-pill btn-yellow w-100" id="btn-masuk">
            <i class="bi bi-send-fill"></i> Kirim kode masuk
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Cegah klik dobel: tombol dinonaktifkan setelah form dikirim
    document.getElementById('form-masuk').addEventListener('submit', function () {
        const btn = document.getElementById('btn-masuk');
        btn.disabled = true;
        btn.innerHTML = 'Memproses...';
    });
</script>
@endpush
