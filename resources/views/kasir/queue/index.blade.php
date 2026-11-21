@extends('layouts.kasir')

@section('title', 'Antrean Cucian')

@section('content')
<style>
    /* Papan antrean: kolom berjajar ke samping (bisa digeser di layar sempit), tiap kolom minimal 240px */
    .queue-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 6px; }
    .queue-col { flex: 1 0 240px; background: var(--mint); border-radius: 14px; padding: 8px; }
    .queue-card { background: #fff; border-radius: 12px; padding: 8px 10px; margin-bottom: 8px; font-size: .86rem; }
    .queue-card .inv { font-weight: 800; white-space: nowrap; font-size: .82rem; }
    .queue-card .late { display: inline-block; font-size: .68rem; padding: 1px 8px; border-radius: 999px; background: #e05a6b; color: #fff; white-space: nowrap; }
    .queue-card .pos-badge { display: inline-block; font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--mint); color: var(--teal); border: 1px solid var(--mint-line); white-space: nowrap; }
    .queue-card .btn { white-space: nowrap; }
    /* Tombol kembali: kecil & samar supaya tidak tertukar dengan tombol maju */
    .queue-card .btn-back { font-size: .75rem; padding: 2px 10px; }
    .queue-card .status-update-box { background: #f8fbfb; border: 1px solid var(--mint-line); border-radius: 10px; padding: 6px 8px; margin-top: 8px; }
</style>

<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h2 class="mb-0"><i class="bi bi-basket3-fill"></i> Antrean Cucian</h2>
        <a href="{{ route('kasir.queue.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-clockwise"></i> Muat Ulang Antrean
        </a>
    </div>
    <p class="opacity-75 mb-3">
        Pilih tahap cucian lalu tekan <b>Simpan</b> (atau <b>Batal</b> untuk membatalkan pilihan), atau gunakan tombol cepat ke tahap berikutnya.
        Pelanggan langsung melihat perubahannya secara real-time di halaman Lacak Nota.
    </p>

    @error('queue') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <div class="queue-board">
        @foreach($columns as $status => $orders)
            <div class="queue-col">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <b>{{ \App\Models\Order::STATUSES[$status] }}</b>
                    <span class="badge rounded-pill" style="background:var(--teal)">{{ $orders->count() }}</span>
                </div>

                @forelse($orders as $o)
                    @php $terlambat = $o->estimate_date && $o->estimate_date->isPast() && $status !== 'ready_for_pickup'; @endphp
                    <div class="queue-card" style="border:1.5px solid {{ $terlambat ? '#e05a6b' : 'var(--mint-line)' }}">
                        <div class="d-flex justify-content-between align-items-center gap-1 flex-wrap">
                            <div class="inv">{{ $o->invoice_number }}</div>
                            <span class="pos-badge">Posisi: {{ \App\Models\Order::STATUSES[$o->order_status] }}</span>
                        </div>
                        @if($terlambat) <span class="late mt-1"><i class="bi bi-clock-history"></i> Lewat target</span> @endif
                        <div class="fw-bold mt-1">{{ $o->customer->name ?? '-' }}</div>
                        <div class="opacity-75">
                            @foreach($o->items as $item)
                                {{ $item->service->name ?? '-' }} ({{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->service->unit ?? '' }})@if(! $loop->last), @endif
                            @endforeach
                        </div>
                        <div class="opacity-75">Target: {{ $o->estimate_date?->format('d-m H:i') }}</div>

                        <!-- Menu Pilih Status dengan tombol Simpan & Batal (Cancel) agar status selalu sinkron/up-to-date -->
                        <form method="POST" action="{{ route('kasir.queue.move', $o) }}" class="status-update-box">
                            @csrf
                            <label class="form-label small fw-semibold mb-1 d-block" style="font-size:.74rem;">Ubah Status Cucian:</label>
                            <select name="status" class="form-select form-select-sm mb-2 queue-status-select"
                                    data-original="{{ $o->order_status }}" style="font-size:.8rem;">
                                @foreach(['received', 'washing', 'drying', 'ironing', 'ready_for_pickup'] as $stKey)
                                    <option value="{{ $stKey }}" @selected($o->order_status === $stKey)>
                                        {{ \App\Models\Order::STATUSES[$stKey] }}{{ $o->order_status === $stKey ? ' (Saat ini)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-sm text-white rounded-pill flex-grow-1" style="background:var(--teal); font-size:.78rem;">
                                    <i class="bi bi-check2-circle"></i> Simpan
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill queue-cancel-btn" style="font-size:.78rem;"
                                        onclick="const sel = this.closest('form').querySelector('.queue-status-select'); if (sel) sel.value = sel.dataset.original;">
                                    <i class="bi bi-x-circle"></i> Batal
                                </button>
                            </div>
                        </form>

                        @if($o->nextStatus())
                            <!-- Tombol cepat maju ke tahap berikutnya -->
                            <form method="POST" action="{{ route('kasir.queue.move', $o) }}" class="d-flex gap-1 mt-2">
                                @csrf
                                <input type="hidden" name="status" value="{{ $o->nextStatus() }}">
                                <button class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1" style="font-size:.78rem;">
                                    <i class="bi bi-arrow-right-circle"></i> Lanjut: {{ \App\Models\Order::STATUSES[$o->nextStatus()] }}
                                </button>
                                @if($o->nextStatus() !== 'ready_for_pickup')
                                    <!-- Lewati langsung ke "Siap diambil" (cth: cuci lipat tanpa setrika) -->
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill" name="status" value="ready_for_pickup" title="Langsung siap diambil">
                                        <i class="bi bi-skip-forward-fill"></i>
                                    </button>
                                @endif
                            </form>
                        @else
                            <!-- Sudah siap diambil: kabari pelanggan lewat WA, serahkan lewat menu Transaksi -->
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @php $wa = \App\Http\Controllers\Kasir\QueueController::readyWaLink($o, $business); @endphp
                                @if($wa)
                                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-success rounded-pill flex-grow-1"><i class="bi bi-whatsapp"></i> Kabari</a>
                                @endif
                                @if(auth()->user()->hasRole('owner', 'kasir'))
                                    <a href="{{ route('kasir.orders.show', $o) }}" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1"><i class="bi bi-bag-check"></i> Serahkan</a>
                                @endif
                            </div>
                        @endif

                        @if($o->previousStatus())
                            <!-- Kembali ke tahap sebelumnya (jika tombol terpencet); minta konfirmasi dulu -->
                            <form method="POST" action="{{ route('kasir.queue.move', $o) }}" class="mt-1 text-end"
                                  onsubmit="return confirm('Kembalikan {{ $o->invoice_number }} ke tahap {{ \App\Models\Order::STATUSES[$o->previousStatus()] }}?')">
                                @csrf
                                <input type="hidden" name="status" value="{{ $o->previousStatus() }}">
                                <button class="btn btn-link btn-back text-secondary text-decoration-none">
                                    <i class="bi bi-arrow-counterclockwise"></i> kembali ke {{ \App\Models\Order::STATUSES[$o->previousStatus()] }}
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="small opacity-75 px-1">Kosong</div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>
@endsection
