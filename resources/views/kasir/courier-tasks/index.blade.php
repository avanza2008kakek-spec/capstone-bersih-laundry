@extends('layouts.kasir')

@section('title', 'Tugas Kurir')

@section('content')
@php
    $tabs = ['aktif' => 'Berjalan', 'done' => 'Selesai', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];
    $tabCount = fn ($t) => $t === 'aktif' ? ($counts['assigned'] ?? 0) + ($counts['on_the_way'] ?? 0) : ($counts[$t] ?? 0);
@endphp
<style>
    .task-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; }
    .task-tabs a { white-space: nowrap; padding: 6px 14px; border-radius: 999px; border: 2px solid var(--mint-line);
                   color: var(--ink); text-decoration: none; font-weight: 700; font-size: .9rem; background: #fff; }
    .task-tabs a.active { background: var(--purple); border-color: var(--purple); color: #fff; }
    .task-card { border: 2px solid var(--mint-line); border-radius: 16px; padding: 12px 14px; margin-bottom: 12px; }
    .task-card.is-failed { border-color: #e05a6b; background: #fff7f8; }
    .task-card dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; margin: 8px 0; font-size: .92rem; }
    .task-card dt { font-weight: 600; opacity: .7; }
    .task-card dd { margin: 0; overflow-wrap: anywhere; }
    .task-card .proof { width: 120px; height: 120px; object-fit: cover; border-radius: 12px; border: 2px solid var(--mint-line); }
    .type-pill { font-size: .75rem; padding: 2px 10px; border-radius: 999px; color: #fff; font-weight: 800; }
</style>

<div class="k-card">
    <h2 class="mb-1"><i class="bi bi-truck"></i> Tugas Kurir</h2>
    <p class="opacity-75 mb-3">
        Tugaskan kurir dari menu <a href="{{ route('kasir.reservations.index') }}" style="color:var(--teal)">Reservasi</a> (jemput)
        atau dari halaman nota yang sudah <b>Siap diambil</b> (antar). Kurir wajib mengambil foto saat menjemput &amp; mengantar.
    </p>

    @error('photo') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    @if($couriers->isEmpty())
        <div class="alert alert-warning py-2 small">
            Belum ada akun kurir.
            @if(auth()->user()->isOwner()) Buat di <a href="{{ route('kasir.staff.create') }}">Kelola Staf</a> dengan role <b>Kurir</b>.
            @else Minta owner membuatnya di menu Kelola Staf. @endif
        </div>
    @endif

    <nav class="task-tabs mb-3">
        @foreach($tabs as $key => $label)
            <a href="{{ route('kasir.courier-tasks.index', ['status' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">
                {{ $label }} <span class="opacity-75">({{ $tabCount($key) }})</span>
            </a>
        @endforeach
    </nav>

    @forelse($tasks as $t)
        <div class="task-card {{ $t->status === 'failed' ? 'is-failed' : '' }}">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <span class="type-pill" style="background:{{ $t->type === 'pickup' ? 'var(--purple)' : 'var(--teal)' }}">
                        <i class="bi {{ $t->type === 'pickup' ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up' }}"></i> {{ $t->typeLabel() }}
                    </span>
                    <div class="fw-bold fs-5 mt-1">{{ $t->customer?->name ?? '-' }}</div>
                </div>
                <span class="badge rounded-pill" style="background:{{ $t->statusColor() }}">{{ $t->statusLabel() }}</span>
            </div>

            <dl>
                <dt>Tanggal</dt> <dd class="fw-bold">{{ $t->scheduled_date->locale('id')->translatedFormat('l, d-m-Y') }}</dd>
                <dt>Kurir</dt>   <dd>{{ $t->courier?->name ?? '-' }}</dd>
                <dt>Alamat</dt>  <dd>{{ $t->address }}</dd>
                @if($t->order)
                    <dt>Nota</dt> <dd><a href="{{ route('kasir.orders.show', $t->order) }}" style="color:var(--teal)">{{ $t->order->invoice_number }}</a></dd>
                @elseif($t->reservation)
                    <dt>Layanan</dt> <dd>{{ $t->reservation->service }}</dd>
                    @if($t->reservation->estimated_weight) <dt>Perkiraan berat</dt> <dd>{{ $t->reservation->estimated_weight }}</dd> @endif
                    @if($t->reservation->estimated_price) <dt>Perkiraan harga</dt> <dd>{{ $t->reservation->estimated_price }}</dd> @endif
                @endif
                @if($t->notes) <dt>Pesan</dt> <dd>{{ $t->notes }}</dd> @endif
                @if($t->courier_notes) <dt>{{ $t->status === 'failed' ? 'Alasan gagal' : 'Catatan kurir' }}</dt> <dd>{{ $t->courier_notes }}</dd> @endif
                @if($t->finished_at) <dt>{{ $t->status === 'done' ? 'Selesai' : 'Waktu' }}</dt> <dd>{{ $t->finished_at->format('d-m-Y H:i') }}</dd> @endif
                @if($t->handover_at) <dt>{{ $t->type === 'pickup' ? 'Diserahkan ke kasir' : 'Diserahkan ke kurir' }}</dt> <dd>{{ $t->handover_at->format('d-m-Y H:i') }}</dd> @endif
            </dl>

            <div class="d-flex flex-wrap gap-3 mb-2">
                @if($t->photo)
                    <!-- Foto bukti di lokasi pelanggan -->
                    <div class="d-flex align-items-end gap-2">
                        <a href="{{ route('kasir.courier-tasks.photo', $t) }}" target="_blank" rel="noopener">
                            <img src="{{ route('kasir.courier-tasks.photo', $t) }}" alt="Foto bukti {{ strtolower($t->typeLabel()) }}" class="proof" loading="lazy">
                        </a>
                        <div class="small">
                            <div class="fw-bold">{{ $t->type === 'pickup' ? 'Foto jemput (pelanggan)' : 'Foto antar (pelanggan)' }}</div>
                            <span class="opacity-75">{{ \App\Models\CourierPhoto::humanSize($t->photo->size) }}</span>
                            @if(auth()->user()->isOwner())
                                <form method="POST" action="{{ route('kasir.courier-tasks.photo.destroy', $t) }}" class="mt-1" onsubmit="return confirm('Hapus foto bukti ini? Foto tidak bisa dikembalikan.')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="kind" value="customer">
                                    <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> Hapus foto</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @elseif($t->status === 'done')
                    <small class="d-block opacity-75"><i class="bi bi-image"></i> Foto bukti sudah dihapus.</small>
                @endif

                @if($t->handoverPhoto)
                    <!-- Foto bukti serah terima antara kurir & kasir di outlet -->
                    <div class="d-flex align-items-end gap-2">
                        <a href="{{ route('kasir.courier-tasks.photo', [$t, 'kind' => 'handover']) }}" target="_blank" rel="noopener">
                            <img src="{{ route('kasir.courier-tasks.photo', [$t, 'kind' => 'handover']) }}" alt="Foto serah terima" class="proof" loading="lazy">
                        </a>
                        <div class="small">
                            <div class="fw-bold">{{ $t->type === 'pickup' ? 'Foto serah ke kasir' : 'Foto serah ke kurir' }}</div>
                            <span class="opacity-75">{{ \App\Models\CourierPhoto::humanSize($t->handoverPhoto->size) }}</span>
                            @if(auth()->user()->isOwner())
                                <form method="POST" action="{{ route('kasir.courier-tasks.photo.destroy', $t) }}" class="mt-1" onsubmit="return confirm('Hapus foto serah terima ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="kind" value="handover">
                                    <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> Hapus foto</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Form kamera serah terima di outlet (jemput: terima dari kurir; antar: serahkan ke kurir) --}}
            @if(! $t->handoverPhoto && in_array($t->status, ['assigned', 'on_the_way', 'done']))
                <form method="POST" action="{{ route('kasir.courier-tasks.handover', $t) }}" enctype="multipart/form-data"
                      class="p-2 rounded-3 mb-2" style="background:var(--mint); border:1px dashed var(--mint-line)">
                    @csrf
                    <div class="small fw-bold mb-1">
                        <i class="bi bi-camera-fill" style="color:var(--teal)"></i>
                        {{ $t->type === 'pickup' ? 'Kurir menyerahkan cucian jemputan ke kasir (Foto kamera)' : 'Kasir menyerahkan cucian ke kurir (Foto kamera)' }}
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label class="btn btn-sm text-white rounded-pill" style="background:var(--teal); cursor:pointer">
                            <i class="bi bi-camera-fill"></i> {{ $t->type === 'pickup' ? 'Foto terima dari kurir' : 'Foto serahkan ke kurir' }}
                            <input type="file" name="photo" accept="image/*" capture="environment" class="d-none js-handover-input" required>
                        </label>
                        <button type="submit" class="btn btn-sm text-white rounded-pill" style="background:var(--purple)">
                            <i class="bi bi-check2-circle"></i> {{ $t->type === 'pickup' ? 'Simpan foto serah ke kasir' : 'Simpan foto serah ke kurir' }}
                        </button>
                    </div>
                    <small class="d-block opacity-75 mt-1 js-handover-info">Belum ada foto dipilih.</small>
                    <img class="js-handover-preview mt-1" alt="Pratinjau foto" style="max-width:100%;max-height:130px;border-radius:10px" hidden>
                </form>
            @endif

            @if($t->isActive())
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- Kirim ulang pemberitahuan tugas ke WhatsApp kurir -->
                    @if($waKurir = $t->waLink($businessName))
                        <a href="{{ $waKurir }}" target="_blank" rel="noopener" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-whatsapp"></i> WA kurir</a>
                    @else
                        <span class="small opacity-75" title="Isi No. HP kurir di Kelola Staf"><i class="bi bi-whatsapp"></i> No. HP kurir kosong</span>
                    @endif
                    <!-- Ganti kurir -->
                    <form method="POST" action="{{ route('kasir.courier-tasks.reassign', $t) }}" class="d-flex gap-1 m-0">
                        @csrf
                        @method('PUT')
                        <select name="courier_id" class="form-select form-select-sm rounded-pill" style="max-width:170px" aria-label="Ganti kurir">
                            @foreach($couriers as $c)
                                <option value="{{ $c->id }}" @selected($c->id == $t->courier_id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill text-nowrap"><i class="bi bi-arrow-repeat"></i> Ganti</button>
                    </form>
                    <form method="POST" action="{{ route('kasir.courier-tasks.cancel', $t) }}" class="m-0" onsubmit="return confirm('Batalkan tugas ini?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-x-circle"></i> Batalkan</button>
                    </form>
                </div>
            @elseif($t->status === 'failed')
                <small class="d-block opacity-75">
                    Tugaskan ulang dari
                    @if($t->order) <a href="{{ route('kasir.orders.show', $t->order) }}" style="color:var(--teal)">halaman nota</a>
                    @else <a href="{{ route('kasir.reservations.index') }}" style="color:var(--teal)">menu Reservasi</a> @endif.
                </small>
            @endif
        </div>
    @empty
        <p class="opacity-75 mb-0">Tidak ada tugas di bagian ini.</p>
    @endforelse

    <div class="mt-2">{{ $tasks->links() }}</div>
</div>
<script>
document.querySelectorAll('.js-handover-input').forEach(function (input) {
    input.addEventListener('change', async function () {
        const form = input.closest('form');
        const info = form.querySelector('.js-handover-info');
        const preview = form.querySelector('.js-handover-preview');
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
