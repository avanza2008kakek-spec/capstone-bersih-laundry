@extends('layouts.public')

@section('title', ($mode === 'login' ? 'Berhasil Masuk' : 'Pendaftaran Berhasil') . ' - ' . $businessName)

@section('content')
<div class="auth-card text-center">
    <!-- Ikon centang besar -->
    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
         style="width:84px;height:84px;border-radius:50%;background:var(--yellow);border:3px solid var(--ink);font-size:2.6rem;">
        <i class="bi bi-check-lg"></i>
    </div>

    <!-- $mode: "login" = baru saja masuk, "register" = baru saja mendaftar -->
    @if($mode === 'login')
        <h2 class="mb-1">Selamat datang kembali, {{ $customer->name }}!</h2>
        <p class="opacity-75 mb-4">Anda sudah masuk dengan nomor WhatsApp <strong>{{ $customer->phone }}</strong>.</p>
    @else
        <h2 class="mb-1">Halo, {{ $customer->name }}!</h2>
        <p class="opacity-75 mb-4">
            Nomor WhatsApp <strong>{{ $customer->phone }}</strong> sudah terverifikasi.
            Sekarang Anda bisa reservasi laundry dan melacak cucian.
        </p>
    @endif

    <div class="d-grid gap-2">
        <a href="{{ route('customer.home') }}#pesan" class="btn-pill btn-yellow"><i class="bi bi-basket3-fill"></i> Reservasi laundry</a>
        <a href="{{ route('customer.home') }}#lacak" class="btn-pill btn-white"><i class="bi bi-search"></i> Lacak cucian</a>
        <!-- Menu Edit Alamat: atur alamat default & lokasi Google Maps -->
        <a href="{{ route('address.edit') }}" class="btn-pill btn-white"><i class="bi bi-geo-alt-fill"></i> Edit alamat</a>
        <!-- Tombol keluar: POST + token CSRF supaya tidak bisa dipicu dari link luar -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link-plain mt-2"><i class="bi bi-box-arrow-right"></i> Keluar</button>
        </form>
    </div>
</div>
@endsection
