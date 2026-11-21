@extends('layouts.kasir')

@section('title', 'Reservasi')

@section('content')
@php
    use App\Http\Controllers\Kasir\ReservationController as R;
    $tabs = ['aktif' => 'Perlu dijemput', 'new' => 'Baru', 'confirmed' => 'Dikonfirmasi', 'picked_up' => 'Sudah dijemput', 'cancelled' => 'Dibatalkan'];
    $tabCount = fn ($t) => $t === 'aktif' ? ($counts['new'] ?? 0) + ($counts['confirmed'] ?? 0) : ($counts[$t] ?? 0);
    $colors = ['new' => '#e05a6b', 'confirmed' => '#e0a14a', 'picked_up' => '#47a990', 'cancelled' => '#9aa5b1'];
    $canOrder = auth()->user()->hasRole('owner', 'kasir');
@endphp
<style>
    /* Tab status: di HP bisa digeser ke samping */
    .resv-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; }
    .resv-tabs a { white-space: nowrap; padding: 6px 14px; border-radius: 999px; border: 2px solid var(--mint-line);
                   color: var(--ink); text-decoration: none; font-weight: 700; font-size: .9rem; background: #fff; }
    .resv-tabs a.active { background: var(--purple); border-color: var(--purple); color: #fff; }
    .resv-card { border: 2px solid var(--mint-line); border-radius: 16px; padding: 12px 14px; margin-bottom: 12px; }
    .resv-card.is-new { border-color: #e05a6b; background: #fff7f8; }
    .resv-card .btn { white-space: nowrap; }
    .resv-card dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; margin: 8px 0; font-size: .92rem; }
    .resv-card dt { font-weight: 600; opacity: .7; }
    .resv-card dd { margin: 0; overflow-wrap: anywhere; }
</style>

<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h2 class="mb-0"><i class="bi bi-bell-fill"></i> Reservasi</h2>
        <!-- Izin notifikasi browser (muncul walau tab kasir sedang tidak dibuka) -->
        <button type="button" id="notif-izin" class="btn btn-sm btn-outline-secondary rounded-pill d-none">
            <i class="bi bi-bell"></i> Aktifkan notifikasi
        </button>
    </div>
    <p class="opacity-75 mb-3">
        Reservasi jemput dari website. Halaman kasir mengecek reservasi baru otomatis tiap 30 detik dan berbunyi saat ada yang masuk.
    </p>

    @error('courier') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @error('courier_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <nav class="resv-tabs mb-3">
        @foreach($tabs as $key => $label)
            <a href="{{ route('kasir.reservations.index', ['status' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">
                {{ $label }} <span class="opacity-75">({{ $tabCount($key) }})</span>
            </a>
        @endforeach
    </nav>

    @forelse($reservations as $r)
        @php $wa = R::waLink($r->customer?->phone); @endphp
        <div class="resv-card {{ $r->status === 'new' ? 'is-new' : '' }}">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold fs-5">{{ $r->customer?->name ?? 'Pelanggan terhapus' }}</div>
                    <small class="opacity-75">Masuk {{ $r->created_at?->format('d-m-Y H:i') }}</small>
                </div>
                <span class="badge rounded-pill" style="background:{{ $colors[$r->status] ?? '#999' }}">{{ $r->statusLabel() }}</span>
            </div>

            <dl>
                <dt>Jemput</dt>     <dd class="fw-bold">{{ $r->pickup_date->locale('id')->translatedFormat('l, d-m-Y') }}
                                        @if($r->pickup_date->isToday()) <span class="badge bg-danger">Hari ini</span>
                                        @elseif($r->pickup_date->isPast() && in_array($r->status, ['new', 'confirmed'])) <span class="badge bg-secondary">Terlewat</span> @endif</dd>
                <dt>Layanan</dt>    <dd>{{ $r->service }}</dd>
                @if($r->estimated_weight) <dt>Perkiraan berat</dt> <dd>{{ $r->estimated_weight }}</dd> @endif
                @if($r->estimated_price) <dt>Perkiraan harga</dt> <dd>{{ $r->estimated_price }}</dd> @endif
                <dt>Alamat</dt>     <dd>{{ $r->address }}</dd>
                <dt>Ongkos</dt>     <dd>{{ $r->pickup_fee ?: '-' }}</dd>
                <dt>No. WA</dt>     <dd>{{ $r->customer?->phone ?? '-' }}</dd>
                @if($r->notes) <dt>Catatan</dt> <dd>{{ $r->notes }}</dd> @endif
                @if($r->handler) <dt>Diproses</dt> <dd class="small opacity-75">{{ $r->handler->name }} · {{ $r->handled_at?->format('d-m H:i') }}</dd> @endif
            </dl>

            <div class="d-flex flex-wrap gap-2">
                @if($wa) <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-whatsapp"></i> Chat</a> @endif
                @if($r->maps_url) <a href="{{ $r->maps_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-geo-alt-fill"></i> Peta</a> @endif

                {{-- Tombol status: [status tujuan, label, kelas tombol, ikon] --}}
                @php
                    $actions = [
                        'new'       => [['confirmed', 'Konfirmasi', 'text-white', 'bi-check2-circle', 'background:var(--teal)'], ['cancelled', 'Batalkan', 'btn-outline-danger', 'bi-x-circle', '']],
                        'confirmed' => [['picked_up', 'Sudah dijemput', 'text-white', 'bi-truck', 'background:var(--teal)'], ['cancelled', 'Batalkan', 'btn-outline-danger', 'bi-x-circle', ''], ['new', 'Kembali ke Baru', 'btn-outline-secondary', 'bi-arrow-counterclockwise', '']],
                        'picked_up' => [['confirmed', 'Belum dijemput', 'btn-outline-secondary', 'bi-arrow-counterclockwise', '']],
                        'cancelled' => [['new', 'Aktifkan lagi', 'btn-outline-secondary', 'bi-arrow-counterclockwise', '']],
                    ][$r->status] ?? [];
                @endphp
                @foreach($actions as [$to, $label, $cls, $icon, $style])
                    <form method="POST" action="{{ route('kasir.reservations.update', $r) }}" class="m-0"
                          @if($to === 'cancelled') onsubmit="return confirm('Batalkan reservasi ini?')" @endif>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="{{ $to }}">
                        <button class="btn btn-sm rounded-pill {{ $cls }}" style="{{ $style }}"><i class="bi {{ $icon }}"></i> {{ $label }}</button>
                    </form>
                @endforeach

                {{-- Setelah cucian dijemput: buat nota dengan pelanggan langsung terpilih --}}
                @if($canOrder && $r->status === 'picked_up' && $r->customer)
                    <a href="{{ route('kasir.orders.create', ['pelanggan' => $r->customer_id]) }}" class="btn btn-sm text-white rounded-pill" style="background:var(--purple)">
                        <i class="bi bi-plus-circle"></i> Buat Nota
                    </a>
                @endif
            </div>

            {{-- Kurir penjemput: tugas yang sedang berjalan / terakhir, atau form "Tugaskan jemput" --}}
            @if($r->relationLoaded('courierTasks'))
                @php $task = $r->courierTasks->first(fn ($t) => $t->isActive()) ?? $r->courierTasks->first(); @endphp {{-- Utamakan tugas yang sedang berjalan --}}
                @if($task && ($task->isActive() || $task->status === 'done'))
                    <div class="mt-2 p-2 rounded-3 small d-flex flex-wrap align-items-center gap-2" style="background:var(--mint)">
                        <i class="bi bi-truck fs-5" style="color:var(--teal)"></i>
                        <span>Kurir: <b>{{ $task->courier?->name ?? '-' }}</b></span>
                        <span class="badge rounded-pill" style="background:{{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                        @if($task->photo)
                            <a href="{{ route('kasir.courier-tasks.photo', $task) }}" target="_blank" rel="noopener" style="color:var(--teal)"><i class="bi bi-image"></i> Foto jemput</a>
                        @endif
                        @if($task->handoverPhoto)
                            <a href="{{ route('kasir.courier-tasks.photo', [$task, 'kind' => 'handover']) }}" target="_blank" rel="noopener" style="color:var(--purple)"><i class="bi bi-camera-fill"></i> Foto serah ke kasir</a>
                        @elseif($canOrder && $task->status === 'done')
                            <form method="POST" action="{{ route('kasir.courier-tasks.handover', $task) }}" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-1 m-0">
                                @csrf
                                <label class="btn btn-sm text-white rounded-pill py-0 px-2" style="background:var(--teal); cursor:pointer" title="Ambil foto saat kurir menyerahkan cucian ke kasir">
                                    <i class="bi bi-camera-fill"></i> Foto serah ke kasir
                                    <input type="file" name="photo" accept="image/*" capture="environment" class="d-none" onchange="this.form.submit()">
                                </label>
                            </form>
                        @endif
                    </div>
                @elseif($couriers && in_array($r->status, ['new', 'confirmed']))
                    <div class="mt-2 p-2 rounded-3" style="background:#fafafa; border:1px dashed var(--mint-line)">
                        @if($task && $task->status === 'failed')
                            <div class="small mb-1" style="color:#e05a6b"><i class="bi bi-exclamation-triangle"></i> Penjemputan oleh {{ $task->courier?->name }} gagal: {{ $task->courier_notes }}</div>
                        @endif
                        <div class="small fw-bold mb-1"><i class="bi bi-truck"></i> Tugaskan kurir untuk menjemput</div>
                        @include('kasir.courier-tasks._assign', ['action' => route('kasir.courier-tasks.pickup', $r), 'couriers' => $couriers])
                    </div>
                @endif
            @endif
        </div>
    @empty
        <p class="opacity-75 mb-0">Tidak ada reservasi di bagian ini.</p>
    @endforelse

    <div class="mt-2">{{ $reservations->links() }}</div>
</div>
@endsection
