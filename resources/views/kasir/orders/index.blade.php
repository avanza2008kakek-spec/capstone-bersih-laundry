@extends('layouts.kasir')

@section('title', 'Transaksi')

@section('content')
<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-receipt"></i> Transaksi</h2>
            <p class="opacity-75 mb-0">Daftar nota. Klik nota untuk melihat detail, menerima pelunasan, atau mencetak.</p>
        </div>
        <a href="{{ route('kasir.orders.create') }}" class="btn-pill btn-yellow"><i class="bi bi-plus-lg"></i> Nota baru</a>
    </div>

    <!-- Saringan: cari teks, status cucian, status bayar, tanggal (dikirim lewat alamat ?q=...&status=...) -->
    <form method="GET" class="row g-2 mb-3">
        <!-- autofocus: alat pindai barcode langsung mengetik ke kotak ini lalu menekan Enter → nota terbuka -->
        <div class="col-md-4"><input type="search" name="q" value="{{ $q }}" class="form-control" autofocus
                                     placeholder="Cari / pindai barcode nota, nama, no. HP"></div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-control">
                <option value="">Semua status</option>
                @foreach(\App\Models\Order::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="bayar" class="form-select form-control">
                <option value="">Semua bayar</option>
                @foreach(\App\Models\Order::PAYMENT_STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(request('bayar') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-7 col-md-2"><input type="date" name="tanggal" value="{{ request('tanggal') }}" class="form-control"></div>
        <div class="col-5 col-md-2 d-grid"><button class="btn-pill btn-white py-1"><i class="bi bi-funnel"></i> Saring</button></div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>No. nota</th><th>Pelanggan</th><th>Tanggal</th><th class="text-end">Total</th><th>Bayar</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($orders as $o)
                    <tr>
                        <td><a href="{{ route('kasir.orders.show', $o) }}" class="fw-bold" style="color:var(--teal)">{{ $o->invoice_number }}</a></td>
                        <td>{{ $o->customer->name ?? '-' }}<br><small class="opacity-75">{{ $o->customer->phone ?? '' }}</small></td>
                        <td class="text-nowrap">{{ $o->order_date?->format('d-m-Y H:i') }}</td>
                        <td class="text-end text-nowrap">Rp{{ number_format((float) $o->final_amount, 0, ',', '.') }}</td>
                        <td>@include('kasir.orders._pay-badge', ['order' => $o])</td>
                        <td>@include('kasir.orders._status-badge', ['order' => $o])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center opacity-75 py-4">Belum ada nota yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</div>
@endsection
