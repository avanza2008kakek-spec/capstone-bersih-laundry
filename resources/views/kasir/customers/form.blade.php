@extends('layouts.kasir')

{{-- Satu form untuk Tambah pelanggan (belum tersimpan) dan Ubah pelanggan (sudah ada) --}}
@php $isNew = ! $customer->exists; @endphp

@section('title', $isNew ? 'Tambah Pelanggan' : 'Ubah Pelanggan')

@section('content')
<div class="k-card" style="max-width: 620px;">
    <a href="{{ $isNew ? route('kasir.customers.index') : route('kasir.customers.show', $customer) }}" class="small" style="color:var(--teal)">&larr; Kembali</a>
    <h2 class="mt-2 mb-3">{{ $isNew ? 'Tambah pelanggan' : 'Ubah pelanggan' }}</h2>

    <form method="POST" action="{{ $isNew ? route('kasir.customers.store') : route('kasir.customers.update', $customer) }}">
        @csrf
        @unless($isNew) @method('PUT') @endunless

        <div class="mb-3">
            <label class="form-label fw-bold" for="name">Nama</label>
            <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" required maxlength="255"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="phone">No. HP / WhatsApp</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" required maxlength="30" placeholder="08xxxxxxxxxx"
                   class="form-control @error('phone') is-invalid @enderror">
            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @unless($isNew)
                <small class="opacity-75">Jika no. HP diganti, tanda "terverifikasi WA" pelanggan ini dicabut.</small>
            @endunless
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold" for="address">Alamat <small class="fw-normal opacity-75">(opsional)</small></label>
            <textarea id="address" name="address" rows="3" maxlength="1000" class="form-control @error('address') is-invalid @enderror">{{ old('address', $customer->address) }}</textarea>
            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan</button>
    </form>
</div>
@endsection
