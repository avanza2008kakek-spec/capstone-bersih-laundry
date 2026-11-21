@extends('layouts.kasir')

@section('title', $customer->name)

@section('content')
<div class="k-card">
    <a href="{{ route('kasir.customers.index') }}" class="small" style="color:var(--teal)">&larr; Kembali ke Pelanggan</a>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
        <div>
            <h2 class="mb-0">{{ $customer->name }}
                @if($customer->phone_verified_at) <i class="bi bi-patch-check-fill fs-5" style="color:var(--teal)" title="Nomor WA terverifikasi"></i> @endif
            </h2>
            <div>{{ $customer->phone }}</div>
            @if($customer->address) <small class="opacity-75">{{ $customer->address }}</small> @endif
            @if($customer->maps_url)
                <div><a href="{{ $customer->maps_url }}" target="_blank" rel="noopener" class="small" style="color:var(--teal)"><i class="bi bi-geo-alt-fill"></i> Lokasi di Google Maps</a></div>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('kasir.customers.edit', $customer) }}" class="btn-pill btn-white py-1 px-3"><i class="bi bi-pencil-square"></i> Ubah</a>
            <a href="{{ route('kasir.orders.create', ['pelanggan' => $customer->id]) }}" class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-plus-lg"></i> Nota baru</a>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="p-2 rounded-3" style="background:var(--mint)"><small>Jumlah nota</small><div class="fs-4 fw-bold">{{ $orders->count() }}</div></div></div>
        <div class="col-6 col-md-3"><div class="p-2 rounded-3" style="background:#fdeef2"><small>Sisa tagihan</small>
            <div class="fs-4 fw-bold" style="color:{{ $unpaid > 0 ? '#e05a6b' : 'var(--teal)' }}">Rp{{ number_format($unpaid, 0, ',', '.') }}</div></div></div>
    </div>

    <h5>Riwayat nota</h5>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>No. nota</th><th>Tanggal</th><th class="text-end">Tagihan</th><th class="text-end">Sisa</th><th>Bayar</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($orders as $o)
                    <tr>
                        <td><a href="{{ route('kasir.orders.show', $o) }}" class="fw-bold" style="color:var(--teal)">{{ $o->invoice_number }}</a></td>
                        <td class="text-nowrap">{{ $o->order_date?->format('d-m-Y') }}</td>
                        <td class="text-end">Rp{{ number_format((float) $o->final_amount, 0, ',', '.') }}</td>
                        <td class="text-end">Rp{{ number_format($o->remaining(), 0, ',', '.') }}</td>
                        <td>@include('kasir.orders._pay-badge', ['order' => $o])</td>
                        <td>@include('kasir.orders._status-badge', ['order' => $o])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center opacity-75 py-3">Belum ada nota.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
