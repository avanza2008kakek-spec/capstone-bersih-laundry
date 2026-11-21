@extends('layouts.kasir')

@section('title', 'Foto Bukti')

@section('content')
@php use App\Models\CourierPhoto; @endphp
<style>
    .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .photo-item { border: 2px solid var(--mint-line); border-radius: 14px; overflow: hidden; background: #fff; font-size: .82rem; }
    .photo-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--mint); }
    .photo-item .info { padding: 6px 8px; }
    @media (max-width: 400px) { .photo-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; } }
</style>

<div class="k-card">
    <h2 class="mb-1"><i class="bi bi-images"></i> Foto Bukti Kurir</h2>
    <p class="opacity-75 mb-3">
        Foto jemput &amp; antar disimpan langsung di database dan sudah diperkecil (maks. 1024 px, ± 50–300 KB per foto).
        Hapus foto lama secara berkala supaya database tetap ringan. Tugasnya tetap tercatat walau fotonya dihapus.
    </p>

    <!-- Ringkasan pemakaian database -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4"><div class="p-2 rounded-3 h-100" style="background:var(--mint)"><small>Jumlah foto</small><div class="fs-4 fw-bold">{{ number_format($total['count'], 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-4"><div class="p-2 rounded-3 h-100" style="background:#fff3bf"><small>Ukuran di database</small><div class="fs-4 fw-bold">{{ CourierPhoto::humanSize($total['bytes']) }}</div></div></div>
        <div class="col-12 col-md-4"><div class="p-2 rounded-3 h-100" style="background:#eef3ff"><small>Foto terlama</small><div class="fs-6 fw-bold">{{ $oldest ? \Illuminate\Support\Carbon::parse($oldest)->format('d-m-Y') : '-' }}</div></div></div>
    </div>

    <!-- Hapus massal: foto yang lebih lama dari N hari -->
    <form method="POST" action="{{ route('kasir.courier-photos.destroy-old') }}" class="d-flex flex-wrap align-items-center gap-2 p-2 rounded-3 mb-3"
          style="background:#fff7f8; border:1px dashed #e05a6b" onsubmit="return confirm('Hapus semua foto yang lebih lama dari ' + this.days.value + ' hari? Foto tidak bisa dikembalikan.')">
        @csrf
        @method('DELETE')
        <span class="fw-bold small"><i class="bi bi-trash3"></i> Hapus foto lebih lama dari</span>
        <select name="days" class="form-select form-select-sm rounded-pill" style="width:auto" aria-label="Umur foto">
            @foreach([7, 30, 60, 90, 180] as $d)
                <option value="{{ $d }}" @selected($d === 90)>{{ $d }} hari</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-danger rounded-pill">Hapus</button>
    </form>

    @if($photos->isEmpty())
        <p class="opacity-75 mb-0">Belum ada foto bukti.</p>
    @else
        <div class="photo-grid">
            @foreach($photos as $p)
                @php
                    $t = $p->task;
                    $photoUrl = $t ? route('kasir.courier-tasks.photo', $p->kind === 'handover' ? [$t, 'kind' => 'handover'] : $t) : null;
                @endphp
                <div class="photo-item">
                    @if($t)
                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener">
                            <img src="{{ $photoUrl }}" alt="Foto bukti" loading="lazy">
                        </a>
                    @endif
                    <div class="info">
                        <b>{{ $p->kindLabel($t) }}</b> · {{ $t?->customer?->name ?? '-' }}<br>
                        <span class="opacity-75">{{ $p->created_at?->format('d-m-Y H:i') }} · {{ CourierPhoto::humanSize($p->size) }}</span><br>
                        <span class="opacity-75">Kurir: {{ $t?->courier?->name ?? '-' }}</span>
                        @if($t)
                            <form method="POST" action="{{ route('kasir.courier-tasks.photo.destroy', $t) }}" class="mt-1" onsubmit="return confirm('Hapus foto ini?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="kind" value="{{ $p->kind ?? 'customer' }}">
                                <button class="btn btn-sm btn-outline-danger rounded-pill w-100"><i class="bi bi-trash"></i> Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $photos->links() }}</div>
    @endif
</div>
@endsection
