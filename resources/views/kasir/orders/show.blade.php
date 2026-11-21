@extends('layouts.kasir')

@section('title', 'Nota ' . $order->invoice_number)

@section('content')
<div class="k-card">
    <a href="{{ route('kasir.orders.index') }}" class="small" style="color:var(--teal)">&larr; Kembali ke Transaksi</a>

    <!-- Kepala nota: nomor, status, tombol aksi -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
        <div>
            <h2 class="mb-1">{{ $order->invoice_number }}</h2>
            @include('kasir.orders._status-badge', ['order' => $order])
            @include('kasir.orders._pay-badge', ['order' => $order])
        </div>
        <div class="d-flex flex-wrap gap-2">
            <!-- Cetak nota di tab baru (format struk) -->
            <a href="{{ route('kasir.orders.receipt', $order) }}" target="_blank" class="btn-pill btn-white py-1 px-3"><i class="bi bi-printer"></i> Cetak nota</a>
            @if($waLink)
                <!-- Kirim ringkasan nota + link lacak ke WhatsApp pelanggan (kasir yang menekan Kirim di WA) -->
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-whatsapp"></i> Kirim nota ke WA</a>
            @endif
        </div>
    </div>

    @foreach(['pickup', 'amount', 'courier', 'courier_id', 'scheduled_date', 'address', 'photo', 'handover_photo'] as $field)
        @error($field) <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @endforeach

    <div class="row g-3">
        <!-- Data pelanggan & waktu -->
        <div class="col-md-5">
            <div class="p-3 rounded-3 h-100" style="background:var(--mint)">
                <small class="opacity-75">Pelanggan</small>
                <div class="fw-bold">
                    @if($order->customer)
                        <a href="{{ route('kasir.customers.show', $order->customer) }}" style="color:var(--ink)">{{ $order->customer->name }}</a>
                    @else - @endif
                </div>
                <div>{{ $order->customer->phone ?? '' }}</div>
                @if($order->customer?->address) <small class="d-block opacity-75">{{ $order->customer->address }}</small> @endif
                <hr class="my-2">
                <small class="opacity-75">Masuk</small><div>{{ $order->order_date?->format('d-m-Y H:i') }} · {{ $order->user->name ?? '-' }}</div>
                <small class="opacity-75">Estimasi selesai</small><div class="fw-bold">{{ $order->estimate_date?->format('d-m-Y H:i') }}</div>
                @if($order->completion_date) <small class="opacity-75">Diambil</small><div>{{ $order->completion_date->format('d-m-Y H:i') }}</div> @endif
                @if($order->notes) <small class="opacity-75">Catatan</small><div>{{ $order->notes }}</div> @endif
            </div>
        </div>

        <!-- Rincian layanan & tagihan -->
        <div class="col-md-7">
            <table class="table table-sm align-middle">
                <thead><tr><th>Layanan</th><th class="text-end">Jumlah</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->service->name ?? '-' }}
                                @if($item->notes) <br><small class="opacity-75">{{ $item->notes }}</small> @endif</td>
                            <td class="text-end text-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->service->unit ?? '' }}
                                <br><small class="opacity-75">@ Rp{{ number_format((float) $item->unit_price, 0, ',', '.') }}</small></td>
                            <td class="text-end">Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="2" class="text-end">Total</td><td class="text-end">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</td></tr>
                    @if((float) $order->discount > 0)
                        <tr><td colspan="2" class="text-end">Diskon</td><td class="text-end">-Rp{{ number_format((float) $order->discount, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="fw-bold"><td colspan="2" class="text-end">Tagihan</td><td class="text-end">Rp{{ number_format((float) $order->final_amount, 0, ',', '.') }}</td></tr>
                    <tr><td colspan="2" class="text-end">Sudah dibayar</td><td class="text-end">Rp{{ number_format((float) $order->paid_amount, 0, ',', '.') }}</td></tr>
                    <tr class="fw-bold" style="color:{{ $order->remaining() > 0 ? '#e05a6b' : 'var(--teal)' }}">
                        <td colspan="2" class="text-end">Sisa</td><td class="text-end">Rp{{ number_format($order->remaining(), 0, ',', '.') }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <!-- Terima pembayaran / pelunasan (hanya jika masih ada sisa) -->
        @if($order->remaining() > 0)
            @php
                $paySettings = app(\App\Support\Settings::class);
                $bankAccounts = $paySettings->bankAccounts();
                $qrisImage = $paySettings->qrisImage();
            @endphp
            <div class="col-md-6">
                <form method="POST" action="{{ route('kasir.orders.pay', $order) }}" class="p-3 rounded-3 h-100" style="background:#fff3bf">
                    @csrf
                    <h5 class="mb-2"><i class="bi bi-cash-coin"></i> Terima pembayaran</h5>
                    <div class="row g-2">
                        <div class="col-6"><input type="number" name="amount" min="1" step="any" max="{{ $order->remaining() }}"
                               value="{{ old('amount', $order->remaining()) }}" class="form-control" required></div>
                        <div class="col-6">
                            <select name="payment_method" class="form-select form-control">
                                @foreach(\App\Models\Order::PAYMENT_METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><button class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-check-lg"></i> Simpan pembayaran</button></div>
                    </div>

                    @if(count($bankAccounts) > 0 || $qrisImage)
                        <hr class="my-3">
                        <div class="small">
                            @if(count($bankAccounts) > 0)
                                <div class="fw-bold mb-1"><i class="bi bi-bank"></i> Rekening Transfer Pembayaran:</div>
                                @foreach($bankAccounts as $acc)
                                    <div class="mb-1">
                                        <b>{{ $acc['bank'] }}</b> · <span class="font-monospace fw-bold">{{ $acc['number'] }}</span> (a.n. {{ $acc['holder'] }})
                                    </div>
                                @endforeach
                            @endif
                            @if($qrisImage)
                                <div class="mt-2">
                                    <div class="fw-bold mb-1"><i class="bi bi-qr-code-scan"></i> QRIS Pembayaran:</div>
                                    <img src="{{ $qrisImage }}" alt="QRIS Pembayaran" style="max-width:150px;max-height:180px;border-radius:10px;border:2px solid #fff;background:#fff;padding:4px">
                                </div>
                            @endif
                        </div>
                    @endif
                </form>
            </div>
        @endif

        <!-- Antar via kurir: tugas yang berjalan/terakhir, atau form tugaskan antar (siap diambil) -->
        @if($courierReady)
            @php $antar = $order->courierTasks->first(fn ($t) => $t->isActive()) ?? $order->courierTasks->first(); @endphp {{-- Utamakan tugas yang sedang berjalan --}}
            @if($antar && ($antar->isActive() || $antar->status === 'done'))
                <div class="col-md-6">
                    <div class="p-3 rounded-3 h-100" style="background:#eef3ff">
                        <h5 class="mb-2"><i class="bi bi-truck"></i> Antar via kurir</h5>
                        <div class="small">
                            Kurir: <b>{{ $antar->courier?->name ?? '-' }}</b>
                            <span class="badge rounded-pill" style="background:{{ $antar->statusColor() }}">{{ $antar->statusLabel() }}</span><br>
                            Tanggal: {{ $antar->scheduled_date->format('d-m-Y') }} · {{ $antar->address }}
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            @if($antar->handoverPhoto)
                                <div>
                                    <small class="d-block fw-bold">Foto serah ke kurir</small>
                                    <a href="{{ route('kasir.courier-tasks.photo', [$antar, 'kind' => 'handover']) }}" target="_blank" rel="noopener" class="d-inline-block mt-1">
                                        <img src="{{ route('kasir.courier-tasks.photo', [$antar, 'kind' => 'handover']) }}" alt="Foto serah ke kurir" loading="lazy"
                                             style="width:100px;height:100px;object-fit:cover;border-radius:12px;border:2px solid var(--mint-line)">
                                    </a>
                                </div>
                            @endif
                            @if($antar->photo)
                                <div>
                                    <small class="d-block fw-bold">Foto antar ke pelanggan</small>
                                    <a href="{{ route('kasir.courier-tasks.photo', $antar) }}" target="_blank" rel="noopener" class="d-inline-block mt-1">
                                        <img src="{{ route('kasir.courier-tasks.photo', $antar) }}" alt="Foto bukti antar" loading="lazy"
                                             style="width:100px;height:100px;object-fit:cover;border-radius:12px;border:2px solid var(--mint-line)">
                                    </a>
                                </div>
                            @endif
                        </div>
                        @if(! $antar->handoverPhoto && $antar->status !== 'cancelled')
                            <form method="POST" action="{{ route('kasir.courier-tasks.handover', $antar) }}" enctype="multipart/form-data" class="mt-2 p-2 rounded-3" style="background:#fff; border:1px dashed var(--mint-line)">
                                @csrf
                                <div class="small fw-bold mb-1"><i class="bi bi-camera-fill" style="color:var(--teal)"></i> Serahkan cucian ke kurir dengan foto kamera</div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <label class="btn btn-sm text-white rounded-pill" style="background:var(--teal); cursor:pointer">
                                        <i class="bi bi-camera-fill"></i> Ambil foto kamera
                                        <input type="file" name="photo" accept="image/*" capture="environment" class="d-none js-handover-input" required>
                                    </label>
                                    <button type="submit" class="btn btn-sm text-white rounded-pill" style="background:var(--purple)">
                                        <i class="bi bi-check2-circle"></i> Simpan foto serah ke kurir
                                    </button>
                                </div>
                                <small class="d-block opacity-75 mt-1 js-handover-info">Belum ada foto dipilih.</small>
                                <img class="js-handover-preview mt-1" alt="Pratinjau foto" style="max-width:100%;max-height:130px;border-radius:10px" hidden>
                            </form>
                        @endif
                        @if($antar->isActive())
                            <div class="mt-2"><a href="{{ route('kasir.courier-tasks.index') }}" class="small" style="color:var(--teal)">Ganti kurir / batalkan di menu Tugas Kurir</a></div>
                        @endif
                    </div>
                </div>
            @elseif($order->order_status === 'ready_for_pickup')
                <div class="col-md-6">
                    <div class="p-3 rounded-3 h-100" style="background:#eef3ff">
                        <h5 class="mb-2"><i class="bi bi-truck"></i> Antar via kurir</h5>
                        @if($antar && $antar->status === 'failed')
                            <div class="small mb-2" style="color:#e05a6b"><i class="bi bi-exclamation-triangle"></i> Pengantaran oleh {{ $antar->courier?->name }} gagal: {{ $antar->courier_notes }}</div>
                        @endif
                        @if($order->remaining() > 0)
                            <p class="small mb-2">Sisa tagihan <b>Rp{{ number_format($order->remaining(), 0, ',', '.') }}</b> akan ditagih kurir saat mengantar.</p>
                        @endif
                        @include('kasir.courier-tasks._assign', ['action' => route('kasir.courier-tasks.delivery', $order), 'couriers' => $couriers,
                                 'delivery' => true, 'address' => $order->customer?->address])
                    </div>
                </div>
            @endif
        @endif

        <!-- Tandai sudah diambil (siap diambil & lunas) -->
        @if($order->order_status === 'ready_for_pickup')
            <div class="col-md-6">
                <form method="POST" action="{{ route('kasir.orders.pickup', $order) }}" class="p-3 rounded-3" style="background:var(--mint)"
                      onsubmit="return confirm('Cucian nota ini sudah diserahkan ke pelanggan?')">
                    @csrf
                    <h5 class="mb-2"><i class="bi bi-bag-check-fill"></i> Serahkan ke pelanggan</h5>
                    <p class="small mb-2">Cucian sudah siap. {{ $order->remaining() > 0 ? 'Lunasi sisa tagihan dulu.' : 'Tagihan sudah lunas.' }}</p>
                    <button class="btn-pill btn-yellow py-1 px-3" @disabled($order->remaining() > 0)><i class="bi bi-check2-all"></i> Tandai sudah diambil</button>
                </form>
            </div>
        @endif
    </div>

    <!-- Riwayat pembayaran & status -->
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <h6 class="fw-bold">Riwayat pembayaran</h6>
            @forelse($order->payments as $p)
                <div class="small border-bottom py-1 d-flex justify-content-between">
                    <span>{{ $p->payment_date?->format('d-m-Y H:i') }} · {{ \App\Models\Order::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method }} · {{ $p->user->name ?? '-' }}</span>
                    <b>Rp{{ number_format((float) $p->amount, 0, ',', '.') }}</b>
                </div>
            @empty
                <small class="opacity-75">Belum ada pembayaran.</small>
            @endforelse
        </div>
        <div class="col-md-6">
            <h6 class="fw-bold">Riwayat status</h6>
            @foreach($order->statusLogs->sortBy('id') as $log)
                <div class="small border-bottom py-1">
                    {{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d-m-Y H:i') }} ·
                    <b>{{ \App\Models\Order::STATUSES[$log->status] ?? $log->status }}</b> · {{ $log->user->name ?? '-' }}
                    @if($log->notes) <span class="opacity-75">({{ $log->notes }})</span> @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.js-handover-input').forEach(function (input) {
    input.addEventListener('change', async function () {
        const box = input.closest('form, div');
        const info = box.querySelector('.js-handover-info');
        const preview = box.querySelector('.js-handover-preview');
        const file = input.files[0];
        if (!file) return;
        if (preview) { preview.src = URL.createObjectURL(file); preview.hidden = false; }
        if (info) info.textContent = 'Memproses foto...';
        try {
            const img = await createImageBitmap(file);
            const skala = Math.min(1, 1024 / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * skala);
            canvas.height = Math.round(img.height * skala);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.7));
            if (blob) {
                const dt = new DataTransfer();
                dt.items.add(new File([blob], 'serah-terima.jpg', { type: 'image/jpeg' }));
                input.files = dt.files;
            }
            if (info) info.textContent = 'Foto siap (' + Math.round(input.files[0].size / 1024) + ' KB).';
        } catch (e) {
            if (info) info.textContent = 'Foto siap dikirim.';
        }
    });
});
</script>
@endsection
