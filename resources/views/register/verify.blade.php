@extends('layouts.public')

@section('title', 'Verifikasi Nomor - ' . $businessName)

@push('styles')
<style>
    /* Kotak keterangan kode sudah dikirim */
    .send-status { border-radius: 16px; padding: 12px 16px; background: #d9f7e4; font-weight: 600; }
    /* Input kode: huruf besar & berjarak agar mudah dibaca */
    .code-input { font-size: 2rem; letter-spacing: .6em; text-align: center; font-weight: 800; padding-left: .6em; }
</style>
@endpush

@section('content')
<div class="auth-card">
    <!-- $mode: "register" (dari tab Daftar) atau "login" (dari tab Masuk) -->
    <span class="chip">{{ $mode === 'login' ? 'Masuk' : 'Daftar' }} · Verifikasi</span>
    <h2 class="mb-1">Masukkan kode</h2>
    <p class="opacity-75 mb-3">
        Kode 6 digit dikirim ke WhatsApp <strong>{{ $phoneLocal }}</strong>.
        <a href="{{ $mode === 'login' ? route('login.form') : route('register.create') }}">Ganti nomor</a>
    </p>

    <!-- Pesan info (cth: setelah kirim ulang) -->
    @if(session('info'))
        <div class="alert alert-info py-2">{{ session('info') }}</div>
    @endif

    @php
        // Kode dari link WA (verifikasi otomatis); hanya dipakai jika berupa 6 angka
        $autofill = preg_match('/^\d{6}$/', (string) session('autofill_code')) ? session('autofill_code') : null;
    @endphp

    <!-- Keterangan: permintaan kirim kode sudah diterima POSAJA Verification Hub -->
    <div class="send-status mb-4" id="send-status">
        @if($autofill)
            <span class="spinner-border spinner-border-sm me-2"></span> Kode dari link WhatsApp sudah terisi, sedang memverifikasi...
        @else
            <i class="bi bi-whatsapp me-2"></i> Kode sudah dikirim. Cek WhatsApp Anda, lalu <b>ketuk link di pesan</b>
            agar kode terisi otomatis, atau salin kodenya lalu tekan <b>Tempel kode</b>.
        @endif
    </div>

    <!-- Form cek kode -->
    <form method="POST" action="{{ route('register.check') }}" id="form-kode">
        @csrf
        <!-- autocomplete="one-time-code": HP bisa menyarankan kode secara otomatis -->
        <input type="text" name="code" id="code-input" maxlength="6" inputmode="numeric" pattern="\d{6}"
               autocomplete="one-time-code" autofocus required placeholder="••••••"
               value="{{ $autofill }}" data-autofill="{{ $autofill ? '1' : '0' }}"
               class="form-control code-input @error('code') is-invalid @enderror">
        @error('code') <div class="invalid-feedback d-block text-center">{{ $message }}</div> @enderror

        <!-- Tempel kode yang sudah disalin dari WhatsApp (langsung diverifikasi) -->
        <button type="button" class="btn-pill btn-white w-100 mt-3" id="btn-paste">
            <i class="bi bi-clipboard-check"></i> Tempel kode dari WhatsApp
        </button>

        <button type="submit" class="btn-pill btn-yellow w-100 mt-3" id="btn-verify">
            <i class="bi bi-shield-check"></i> {{ $mode === 'login' ? 'Verifikasi & masuk' : 'Verifikasi & daftar' }}
        </button>
    </form>

    <!-- Kirim ulang kode: tombol aktif setelah hitung mundur selesai -->
    <form method="POST" action="{{ route('register.resend') }}" class="text-center mt-3">
        @csrf
        <span class="opacity-75">Belum menerima kode?</span>
        <button type="submit" id="btn-resend" class="link-plain" {{ $resendIn > 0 ? 'disabled' : '' }}>
            Kirim ulang kode<span id="resend-timer">{{ $resendIn > 0 ? " ({$resendIn})" : '' }}</span>
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // 1. Hitung mundur tombol "Kirim ulang kode"
    let sisa = {{ $resendIn }};
    const btnResend = document.getElementById('btn-resend');
    const timer = document.getElementById('resend-timer');
    const hitungMundur = setInterval(() => {
        sisa--;
        if (sisa <= 0) {
            clearInterval(hitungMundur);
            btnResend.disabled = false;                     // Tombol bisa diklik
            timer.textContent = '';
        } else {
            timer.textContent = ' (' + sisa + ')';
        }
    }, 1000);

    // 2. Isi & kirim kode otomatis
    const form = document.getElementById('form-kode');
    const input = document.getElementById('code-input');
    const btnPaste = document.getElementById('btn-paste');
    const statusBox = document.getElementById('send-status');
    let terkirim = false;                                   // Cegah form terkirim dua kali

    // Ambil 6 angka pertama dari teks apa pun (cth: seluruh isi pesan WA yang ikut tersalin)
    function ambilKode(teks) {
        const m = String(teks || '').match(/\b(\d{6})\b/) || String(teks || '').replace(/\D/g, '').match(/^(\d{6})/);
        return m ? m[1] : null;
    }

    // Isi kotak kode lalu kirim formnya
    function isiDanKirim(kode) {
        if (!kode || terkirim) return;
        input.value = kode;
        terkirim = true;
        statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Kode terisi otomatis, sedang memverifikasi...';
        document.getElementById('btn-verify').disabled = true;
        setTimeout(() => form.submit(), 400);               // Jeda singkat agar kode yang terisi sempat terlihat
    }

    // a. Dibuka dari link WA: kode sudah terisi → langsung diverifikasi
    if (input.dataset.autofill === '1') {
        isiDanKirim(input.value);
    }

    // b. Diketik / diisi otomatis oleh keyboard HP: begitu lengkap 6 angka → langsung diverifikasi
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 6);
        if (input.value.length === 6) isiDanKirim(input.value);
    });

    // c. Ditempel (Ctrl+V / tekan lama → Tempel): ambil 6 angkanya walau yang tersalin seluruh pesan
    input.addEventListener('paste', (e) => {
        const kode = ambilKode((e.clipboardData || window.clipboardData).getData('text'));
        if (kode) {
            e.preventDefault();
            isiDanKirim(kode);
        }
    });

    // d. Tombol "Tempel kode dari WhatsApp": baca isi clipboard (browser akan meminta izin sekali)
    btnPaste.addEventListener('click', async () => {
        try {
            const kode = ambilKode(await navigator.clipboard.readText());
            if (kode) {
                isiDanKirim(kode);
            } else {
                statusBox.innerHTML = '<i class="bi bi-info-circle me-2"></i> Belum ada kode 6 angka yang tersalin. Salin kode dari pesan WhatsApp dulu.';
            }
        } catch (err) {
            // Browser menolak akses clipboard → minta pelanggan menempel manual di kotak kode
            input.focus();
            statusBox.innerHTML = '<i class="bi bi-info-circle me-2"></i> Tekan lama kotak kode di atas lalu pilih <b>Tempel</b>.';
        }
    });

    // e. Kembali dari aplikasi WhatsApp: jika izin clipboard sudah pernah diberikan, kode langsung diambil sendiri
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState !== 'visible' || terkirim || !navigator.permissions || !navigator.clipboard) return;
        try {
            const izin = await navigator.permissions.query({ name: 'clipboard-read' });
            if (izin.state !== 'granted') return;
            const teks = (await navigator.clipboard.readText()).trim();
            // Hanya ambil jika isinya persis 6 angka atau pesan verifikasi, supaya angka lain (cth: nomor nota) tidak ikut terkirim
            if (/^\d{6}$/.test(teks) || /verifikasi/i.test(teks)) isiDanKirim(ambilKode(teks));
        } catch (err) { /* Browser tidak mendukung cek izin ini: abaikan */ }
    });
</script>
@endpush
