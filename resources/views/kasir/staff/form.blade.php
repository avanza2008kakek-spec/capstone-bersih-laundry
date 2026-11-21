@extends('layouts.kasir')

{{-- Satu form untuk Tambah staf (staff belum tersimpan) dan Ubah staf (staff sudah ada) --}}
@php $isNew = ! $staff->exists; @endphp

@section('title', $isNew ? 'Tambah Staf' : 'Ubah Staf')

@section('content')
<div class="k-card" style="max-width: 640px;">
    <a href="{{ route('kasir.staff.index') }}" class="small" style="color:var(--teal)">&larr; Kembali ke Kelola Staf</a>
    <h2 class="mt-2 mb-3">
        @if($isNew) <i class="bi bi-person-plus-fill"></i> Tambah staf @else <i class="bi bi-pencil-square"></i> Ubah staf: {{ $staff->name }} @endif
    </h2>

    {{-- Tambah = POST ke /kasir/staf; Ubah = PUT ke /kasir/staf/{id} --}}
    <form method="POST" action="{{ $isNew ? route('kasir.staff.store') : route('kasir.staff.update', $staff) }}">
        @csrf
        @unless($isNew) @method('PUT') @endunless

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold" for="name">Nama</label>
                <input type="text" id="name" name="name" value="{{ old('name', $staff->name) }}" required maxlength="100"
                       class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="phone">No. HP <small class="fw-normal opacity-75">(opsional; untuk kurir wajib diisi agar bisa menerima WA tugas)</small></label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $staff->phone) }}" maxlength="20" placeholder="08xxxxxxxxxx"
                       class="form-control @error('phone') is-invalid @enderror">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-bold" for="email">Email (untuk login)</label>
                <input type="email" id="email" name="email" value="{{ old('email', $staff->email) }}" required maxlength="150" autocomplete="off"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- Pilihan role: menentukan menu yang bisa dibuka staf ini -->
            <div class="col-12">
                <label class="form-label fw-bold d-block">Role</label>
                @foreach(\App\Models\User::ROLES as $key => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="role-{{ $key }}" value="{{ $key }}"
                               @checked(old('role', $staff->roleKey()) === $key)>
                        <label class="form-check-label" for="role-{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                @error('role') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="d-block opacity-75 mt-1">
                    Owner: semua menu · Kasir: transaksi, pembayaran, pelanggan, tugaskan kurir · Operator: antrean &amp; status cucian · Kurir: jemput &amp; antar cucian (foto bukti)
                </small>
            </div>

            <!-- Password: wajib saat tambah, opsional saat ubah (kosongkan jika tidak diganti) -->
            <div class="col-md-6">
                <label class="form-label fw-bold" for="password">{{ $isNew ? 'Password' : 'Password baru' }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password" @required($isNew)
                       placeholder="{{ $isNew ? '' : 'Kosongkan jika tidak diganti' }}"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="opacity-75">Minimal 8 karakter, berisi huruf dan angka.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="password_confirmation">Ulangi password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" @required($isNew)
                       class="form-control">
            </div>

            <div class="col-12">
                <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> {{ $isNew ? 'Buat akun' : 'Simpan perubahan' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
