@extends('layouts.kasir')

@section('title', 'Pengeluaran')

@section('content')
<div class="k-card">
    <h2 class="mb-1"><i class="bi bi-wallet2"></i> Pengeluaran</h2>
    <p class="opacity-75 mb-3">Catat setiap kas keluar (deterjen, plastik, listrik, dll.). Semua catatan masuk ke Laporan Keuangan owner.</p>

    <div class="row g-3">
        <!-- Form catat pengeluaran -->
        <div class="col-lg-5">
            <form method="POST" action="{{ route('kasir.expenses.store') }}" class="p-3 rounded-3" style="background:#fff3bf">
                @csrf
                <h5 class="mb-2">Catat pengeluaran</h5>
                @if($categories->isEmpty())
                    <div class="alert alert-warning py-2 small">Belum ada kategori biaya. {{ auth()->user()->isOwner() ? 'Tambahkan kategori di bawah.' : 'Minta owner menambahkan kategori.' }}</div>
                @endif
                <div class="mb-2">
                    <label class="form-label small fw-bold" for="expense_category_id">Kategori</label>
                    <select id="expense_category_id" name="expense_category_id" required class="form-select form-control @error('expense_category_id') is-invalid @enderror">
                        <option value="">-- Pilih --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('expense_category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="expense_date">Tanggal</label>
                        <input type="date" id="expense_date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required
                               class="form-control @error('expense_date') is-invalid @enderror">
                        @error('expense_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold" for="amount">Nominal (Rp)</label>
                        <input type="number" id="amount" name="amount" min="1" step="any" value="{{ old('amount') }}" required
                               class="form-control @error('amount') is-invalid @enderror">
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold" for="description">Keterangan</label>
                    <input type="text" id="description" name="description" maxlength="500" value="{{ old('description') }}" required placeholder="Cth: beli deterjen 5 liter"
                           class="form-control @error('description') is-invalid @enderror">
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-check-lg"></i> Simpan</button>
            </form>

            @if(auth()->user()->isOwner())
                <!-- Tambah kategori biaya (khusus owner) -->
                <form method="POST" action="{{ route('kasir.expenses.categories.store') }}" class="d-flex gap-2 mt-2">
                    @csrf
                    <input type="text" name="name" maxlength="100" required placeholder="Kategori baru, cth: Listrik"
                           class="form-control form-control-sm @error('name') is-invalid @enderror">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill text-nowrap">+ Kategori</button>
                </form>
                @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
            @endif
        </div>

        <!-- Daftar pengeluaran per tanggal -->
        <div class="col-lg-7">
            <form method="GET" class="d-flex gap-2 align-items-center mb-2">
                <label for="tanggal" class="small fw-bold text-nowrap">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </form>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Kategori</th><th>Keterangan</th><th>Dicatat</th><th class="text-end">Nominal</th>@if(auth()->user()->isOwner())<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse($expenses as $e)
                            <tr>
                                <td>{{ $e->category->name ?? '-' }}</td>
                                <td>{{ $e->description }}</td>
                                <td class="small">{{ $e->user->name ?? '-' }}</td>
                                <td class="text-end text-nowrap">Rp{{ number_format((float) $e->amount, 0, ',', '.') }}</td>
                                @if(auth()->user()->isOwner())
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('kasir.expenses.destroy', $e) }}" onsubmit="return confirm('Hapus catatan pengeluaran ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center opacity-75 py-3">Belum ada pengeluaran pada tanggal ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">Rp{{ number_format((float) $total, 0, ',', '.') }}</td>@if(auth()->user()->isOwner())<td></td>@endif</tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
