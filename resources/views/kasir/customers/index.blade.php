@extends('layouts.kasir')

@section('title', 'Pelanggan')

@section('content')
<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-person-lines-fill"></i> Pelanggan</h2>
            <p class="opacity-75 mb-0">Cari pelanggan, lihat riwayat nota & sisa tagihan.</p>
        </div>
        <a href="{{ route('kasir.customers.create') }}" class="btn-pill btn-yellow"><i class="bi bi-person-plus-fill"></i> Tambah pelanggan</a>
    </div>

    <form method="GET" class="d-flex gap-2 mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama atau no. HP">
        <button class="btn-pill btn-white py-1 px-3"><i class="bi bi-search"></i> Cari</button>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nama</th><th>No. HP</th><th>Alamat</th><th class="text-center">Nota</th><th></th></tr></thead>
            <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td class="fw-bold">
                            {{ $c->name }}
                            @if($c->phone_verified_at) <i class="bi bi-patch-check-fill" style="color:var(--teal)" title="Nomor WA terverifikasi"></i> @endif
                        </td>
                        <td class="text-nowrap">{{ $c->phone }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($c->address, 60) ?: '-' }}</td>
                        <td class="text-center">{{ $c->orders_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('kasir.customers.show', $c) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Detail</a>
                            <a href="{{ route('kasir.orders.create', ['pelanggan' => $c->id]) }}" class="btn btn-sm text-white rounded-pill" style="background:var(--teal)">+ Nota</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center opacity-75 py-4">Belum ada pelanggan yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
@endsection
