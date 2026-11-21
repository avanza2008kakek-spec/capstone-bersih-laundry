@extends('layouts.kasir')

@section('title', 'Log Aktivitas Semua User')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-0"><i class="bi bi-clock-history"></i> Log Aktivitas Semua User</h2>
        <small class="opacity-75">Pantau riwayat aktivitas seluruh akun (Owner, Kasir, Operator, Kurir, dan Pelanggan).</small>
    </div>
    @if($totalCount > 0)
        <form method="POST" action="{{ route('kasir.activity-logs.destroy-old') }}" class="d-flex align-items-center gap-2"
              onsubmit="return confirm('Hapus catatan log aktivitas yang lebih lama dari pilihan hari ini?')">
            @csrf
            @method('DELETE')
            <select name="days" class="form-select form-select-sm rounded-pill" style="width:auto">
                <option value="30">Lebih dari 30 hari</option>
                <option value="60">Lebih dari 60 hari</option>
                <option value="90" selected>Lebih dari 90 hari</option>
                <option value="180">Lebih dari 180 hari</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill text-nowrap">
                <i class="bi bi-trash"></i> Bersihkan log lama
            </button>
        </form>
    @endif
</div>

<!-- Ringkasan jumlah aktivitas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="k-card p-3 text-center h-100">
            <small class="opacity-75 d-block">Hari Ini</small>
            <h3 class="mb-0" style="color:var(--teal)">{{ number_format($todayCount, 0, ',', '.') }}</h3>
            <small class="opacity-75">aktivitas</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="k-card p-3 text-center h-100">
            <small class="opacity-75 d-block">Aktivitas Staff</small>
            <h3 class="mb-0" style="color:var(--purple)">{{ number_format($staffCount, 0, ',', '.') }}</h3>
            <small class="opacity-75">Owner / Kasir / Op / Kurir</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="k-card p-3 text-center h-100">
            <small class="opacity-75 d-block">Aktivitas Pelanggan</small>
            <h3 class="mb-0" style="color:#2a78b0">{{ number_format($customerCount, 0, ',', '.') }}</h3>
            <small class="opacity-75">Reservasi / Masuk / Alamat</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="k-card p-3 text-center h-100">
            <small class="opacity-75 d-block">Total Tercatat</small>
            <h3 class="mb-0">{{ number_format($totalCount, 0, ',', '.') }}</h3>
            <small class="opacity-75">semua waktu</small>
        </div>
    </div>
</div>

<!-- Saringan pencarian log aktivitas -->
<div class="k-card mb-3 p-3">
    <form method="GET" action="{{ route('kasir.activity-logs.index') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold mb-1">Cari Kata Kunci</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nama, nota, jenis perangkat, IP...">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold mb-1">Role User</label>
            <select name="role" class="form-select form-select-sm form-control">
                <option value="">Semua Role</option>
                @foreach($roles as $rKey => $rLabel)
                    <option value="{{ $rKey }}" @selected(request('role') === $rKey)>{{ $rLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold mb-1">Akun Staff</label>
            <select name="user_id" class="form-select form-select-sm form-control">
                <option value="">Semua Staff</option>
                @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected((string) request('user_id') === (string) $s->id)>{{ $s->name }} ({{ $s->roleKey() }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold mb-1">Kategori</label>
            <select name="category" class="form-select form-select-sm form-control">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cKey => $cInfo)
                    <option value="{{ $cKey }}" @selected(request('category') === $cKey)>{{ $cInfo['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold mb-1">Rentang Tanggal</label>
            <div class="input-group input-group-sm">
                <input type="date" name="dari" value="{{ request('dari') }}" class="form-control" title="Dari tanggal">
                <input type="date" name="sampai" value="{{ request('sampai') }}" class="form-control" title="Sampai tanggal">
            </div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 mt-2">
            <button type="submit" class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-funnel-fill"></i> Terapkan Filter</button>
            @if(request()->anyFilled(['q', 'role', 'user_id', 'category', 'dari', 'sampai']))
                <a href="{{ route('kasir.activity-logs.index') }}" class="btn-pill btn-white py-1 px-3"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Daftar log aktivitas -->
<div class="k-card">
    @if($logs->isEmpty())
        <div class="text-center py-4 opacity-75">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            Belum ada catatan aktivitas yang sesuai dengan filter.
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:145px">Waktu</th>
                        <th style="width:165px">User &amp; Role</th>
                        <th style="width:190px">Jenis Perangkat</th>
                        <th style="width:130px">IP Address</th>
                        <th style="width:170px">Kategori</th>
                        <th>Aktivitas &amp; Keterangan</th>
                        <th class="text-end" style="width:90px">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="small text-nowrap">
                                <div class="fw-bold">{{ $log->created_at?->format('d-m-Y H:i:s') }}</div>
                                <span class="opacity-75">{{ $log->created_at?->diffForHumans() }}</span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $log->actor_name }}</div>
                                <span class="badge rounded-pill" style="{{ $log->roleBadgeStyle() }}">{{ $log->roleLabel() }}</span>
                            </td>
                            <td class="small">
                                <div class="fw-bold">
                                    <i class="bi {{ $log->deviceIcon() }}" style="color:var(--teal)"></i>
                                    {{ $log->device ?: '-' }}
                                </div>
                            </td>
                            <td class="small text-nowrap">
                                <span class="badge rounded-pill font-monospace px-2 py-1" style="background:#f4f6f8; color:var(--ink); border:1px solid #dce2e8">
                                    {{ $log->ip_address ?: '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-2 py-1" style="background:var(--mint); color:var(--ink); border:1px solid var(--mint-line)">
                                    <i class="bi {{ $log->categoryIcon() }}" style="color:{{ $log->categoryColor() }}"></i>
                                    {{ $log->categoryLabel() }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $log->action }}</div>
                                @if($log->description)
                                    <div class="small opacity-75">{{ $log->description }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($log->subject_url)
                                    <a href="{{ $log->subject_url }}" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2">
                                        Buka &rarr;
                                    </a>
                                @else
                                    <span class="small opacity-50">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection

