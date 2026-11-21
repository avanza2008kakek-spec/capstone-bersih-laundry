@extends('layouts.kasir')

{{-- Satu form untuk Tambah layanan (belum tersimpan) dan Ubah layanan (sudah ada) --}}
@php $isNew = ! $service->exists; @endphp

@section('title', $isNew ? 'Tambah Layanan' : 'Ubah Layanan')

@section('content')
<div class="k-card" style="max-width: 640px;">
    <a href="{{ route('kasir.services.index') }}" class="small" style="color:var(--teal)">&larr; Kembali ke Layanan &amp; Harga</a>
    <h2 class="mt-2 mb-3">{{ $isNew ? 'Tambah layanan' : 'Ubah layanan' }}</h2>

    @if($categories->isEmpty())
        <div class="alert alert-warning">Belum ada kategori. <a href="{{ route('kasir.services.index') }}">Tambahkan kategori</a> dulu.</div>
    @endif

    <form method="POST" action="{{ $isNew ? route('kasir.services.store') : route('kasir.services.update', $service) }}">
        @csrf
        @unless($isNew) @method('PUT') @endunless

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold" for="name">Nama layanan</label>
                <input type="text" id="name" name="name" value="{{ old('name', $service->name) }}" required maxlength="255" placeholder="Cth: Cuci Kering Setrika"
                       class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="category_id">Kategori</label>
                <select id="category_id" name="category_id" required class="form-select form-control @error('category_id') is-invalid @enderror">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string) old('category_id', $service->category_id) === (string) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="unit">Satuan</label>
                <select id="unit" name="unit" required class="form-select form-control @error('unit') is-invalid @enderror">
                    @foreach(\App\Http\Controllers\Kasir\ServiceController::UNITS as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit', $service->unit) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="price">Harga per satuan (Rp)</label>
                <input type="number" id="price" name="price" min="0" step="100" value="{{ old('price', $service->price !== null ? (int) $service->price : '') }}" required
                       class="form-control @error('price') is-invalid @enderror">
                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="duration_hours">Lama pengerjaan (jam)</label>
                <input type="number" id="duration_hours" name="duration_hours" min="1" max="720" value="{{ old('duration_hours', $service->duration_hours) }}" required
                       class="form-control @error('duration_hours') is-invalid @enderror">
                @error('duration_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="opacity-75">Dipakai untuk estimasi selesai di nota. Cth: 6 (kilat), 24, 48, 72.</small>
            </div>
            <div class="col-12">
                <!-- Kotak centang aktif; hidden "0" dikirim jika tidak dicentang -->
                <input type="hidden" name="is_active" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $service->is_active))>
                    <label class="form-check-label" for="is_active">Aktif (tampil di website & bisa dipilih kasir)</label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan</button>
            </div>
        </div>
    </form>
</div>
@endsection
