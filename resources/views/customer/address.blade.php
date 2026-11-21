@extends('layouts.public')

@section('title', 'Edit Alamat - ' . $businessName)

@section('content')
<div class="auth-card">
    <span class="chip">Akun saya</span>
    <h2 class="mb-1">Edit alamat</h2>
    <p class="opacity-75 mb-3">
        Halo, <strong>{{ $customer->name }}</strong> ({{ $customer->phone }}). Alamat &amp; lokasi ini dipakai otomatis
        sebagai alamat penjemputan setiap kali Anda reservasi.
    </p>

    <!-- Pesan berhasil disimpan -->
    @if(session('status'))
        <div class="alert alert-success py-2"><i class="bi bi-check-circle-fill"></i> {{ session('status') }}</div>
    @endif

    {{-- Form simpan alamat. @@method('PUT') = permintaan ubah data --}}
    <form method="POST" action="{{ route('address.update') }}">
        @csrf
        @method('PUT')
        <!-- next=reservasi: setelah disimpan kembali ke form reservasi -->
        @if($next)
            <input type="hidden" name="next" value="{{ $next }}">
        @endif

        <!-- Isian alamat + lokasi Google Maps (komponen yang sama dengan form reservasi); di sini lokasi wajib -->
        @include('customer._location-fields', [
            'address'      => $customer->address,
            'mapsUrl'      => $customer->maps_url,
            'mapsRequired' => true,
            'showPickupFee' => true,
        ])

        <div class="d-grid gap-2 mt-2">
            <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-bookmark-check-fill"></i> Simpan sebagai alamat default</button>
            <a href="{{ $next === 'reservasi' ? route('customer.home') . '#pesan' : route('customer.home') }}" class="btn-pill btn-white">
                {{ $next === 'reservasi' ? 'Kembali ke reservasi' : 'Kembali ke beranda' }}
            </a>
        </div>
    </form>
</div>
@endsection
