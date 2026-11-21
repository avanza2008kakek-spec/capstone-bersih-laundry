@extends('layouts.kasir')

@section('title', 'Layanan & Harga')

@section('content')
<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-tags-fill"></i> Layanan &amp; Harga</h2>
            <p class="opacity-75 mb-0">Layanan aktif tampil di website & bisa dipilih kasir. Harga baru hanya berlaku untuk nota berikutnya.</p>
        </div>
        <a href="{{ route('kasir.services.create') }}" class="btn-pill btn-yellow"><i class="bi bi-plus-lg"></i> Tambah layanan</a>
    </div>

    @error('service') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <!-- Tambah kategori -->
    <form method="POST" action="{{ route('kasir.services.categories.store') }}" class="d-flex flex-wrap gap-2 mb-3">
        @csrf
        <input type="text" name="name" maxlength="100" required placeholder="Kategori baru, cth: Satuan Premium"
               class="form-control @error('name') is-invalid @enderror" style="max-width:320px">
        <button class="btn-pill btn-white py-1 px-3"><i class="bi bi-folder-plus"></i> Tambah kategori</button>
        @error('name') <div class="w-100 text-danger small">{{ $message }}</div> @enderror
    </form>

    @forelse($categories as $cat)
        <div class="mb-4">
            <!-- Kepala kategori: ganti nama (klik pensil) & hapus jika kosong -->
            <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded-3" style="background:var(--mint)">
                <form method="POST" action="{{ route('kasir.services.categories.update', $cat) }}" class="d-flex gap-1 flex-grow-1">
                    @csrf
                    @method('PUT')
                    <input type="text" name="name" value="{{ $cat->name }}" maxlength="100" required class="form-control form-control-sm fw-bold" style="max-width:260px">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" title="Simpan nama kategori"><i class="bi bi-check-lg"></i></button>
                </form>
                <span class="small opacity-75">{{ $cat->services->count() }} layanan</span>
                @if($cat->services->isEmpty())
                    <form method="POST" action="{{ route('kasir.services.categories.destroy', $cat) }}" onsubmit="return confirm('Hapus kategori {{ addslashes($cat->name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus kategori"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            </div>

            <!-- Daftar layanan: kartu di HP, baris tabel di layar lebar -->
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse($cat->services as $s)
                            <tr class="{{ $s->is_active ? '' : 'opacity-50' }}">
                                <td>
                                    <div class="fw-bold">{{ $s->name }}</div>
                                    <small class="opacity-75"><i class="bi bi-clock"></i> {{ $s->duration_hours }} jam</small>
                                    @unless($s->is_active) <span class="badge bg-secondary">Nonaktif</span> @endunless
                                    {{-- Di HP harga ditaruh di bawah nama supaya nama layanan tidak terhimpit --}}
                                    <div class="d-sm-none fw-bold" style="color:var(--teal)">
                                        Rp{{ number_format((float) $s->price, 0, ',', '.') }}<small class="text-muted fw-normal">/{{ $s->unit }}</small>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell text-end text-nowrap fw-bold" style="color:var(--teal)">
                                    Rp{{ number_format((float) $s->price, 0, ',', '.') }}<small class="text-muted fw-normal">/{{ $s->unit }}</small>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('kasir.services.edit', $s) }}" class="btn btn-sm btn-outline-secondary rounded-pill" title="Ubah"><i class="bi bi-pencil-square"></i></a>
                                    <!-- Aktif/nonaktif -->
                                    <form method="POST" action="{{ route('kasir.services.toggle', $s) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm rounded-pill {{ $s->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <i class="bi {{ $s->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('kasir.services.destroy', $s) }}" class="d-inline"
                                          onsubmit="return confirm('Hapus layanan {{ addslashes($s->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="small opacity-75">Belum ada layanan di kategori ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="opacity-75">Belum ada kategori. Tambahkan kategori dulu, cth: "Kiloan", lalu tambah layanannya.</p>
    @endforelse
</div>
@endsection
