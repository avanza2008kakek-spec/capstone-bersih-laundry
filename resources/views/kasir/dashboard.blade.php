@extends('layouts.kasir')

@section('title', 'Dashboard')

@section('content')
@php
    $u = auth()->user();
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
    $queue = $stats['queue'];
    $sedangDikerjakan = $queue->only(['received', 'washing', 'drying', 'ironing'])->sum();
    // Kartu menu cepat per role: [ikon, judul, keterangan, link, warna latar, role yang boleh]
    $cards = [
        ['bi-plus-circle-fill',  'Nota Baru',        'Terima cucian & buat nota',             route('kasir.orders.create'),   '#fff3bf',     ['owner', 'kasir']],
        ['bi-receipt',           'Transaksi',        'Daftar nota, pelunasan, cetak nota',    route('kasir.orders.index'),    'var(--mint)', ['owner', 'kasir']],
        ['bi-truck',             'Tugas Saya',       'Jemput & antar cucian (wajib foto)',    route('kasir.courier.index'),   '#fff3bf',     ['kurir']],
        ['bi-truck',             'Tugas Kurir',      'Tugaskan jemput & antar, lihat foto',   route('kasir.courier-tasks.index'), '#eef3ff', ['owner', 'kasir']],
        ['bi-bell-fill',         'Reservasi',        'Reservasi jemput dari website',         route('kasir.reservations.index'), '#fdeef2', ['owner', 'kasir', 'operator']],
        ['bi-basket3-fill',      'Antrean Cucian',   'Ubah status: dicuci → siap diambil',    route('kasir.queue.index'),     '#eef3ff',     ['owner', 'kasir', 'operator']],
        ['bi-person-lines-fill', 'Pelanggan',        'Cari & lihat riwayat pelanggan',        route('kasir.customers.index'), '#fdeef2',     ['owner', 'kasir']],
        ['bi-wallet2',           'Pengeluaran',      'Catat kas keluar operasional',          route('kasir.expenses.index'),  '#fff3bf',     ['owner', 'kasir']],
        ['bi-graph-up-arrow',    'Laporan Keuangan', 'Arus kas, laba rugi, piutang',          route('reports.index'),         '#fff3bf',     ['owner']],
        ['bi-tags-fill',         'Layanan & Harga',  'Tambah/ubah layanan, harga, kategori',  route('kasir.services.index'),  'var(--mint)', ['owner']],
        ['bi-people-fill',       'Kelola Staf',      'Buat akun & beri role kasir/operator/kurir',  route('kasir.staff.index'),     '#fdeef2',     ['owner']],
        ['bi-images',            'Foto Bukti',       'Foto jemput/antar kurir, hapus foto lama', route('kasir.courier-photos.index'), '#eef3ff', ['owner']],
        ['bi-gear-fill',         'Pengaturan Usaha', 'Nama, lokasi, ongkos jemput, tema',     route('kasir.settings'),        'var(--mint)', ['owner']],
    ];
    $intro = [
        'owner'    => 'Anda masuk sebagai owner: semua menu bisa dibuka, termasuk laporan keuangan dan kelola staf.',
        'kasir'    => 'Anda masuk sebagai kasir: layani transaksi, pembayaran, dan data pelanggan.',
        'operator' => 'Anda masuk sebagai operator: kerjakan antrean cucian dan perbarui statusnya.',
        'kurir'    => 'Anda masuk sebagai kurir: jemput & antar cucian sesuai tugas dari kasir, dan foto setiap penjemputan/pengantaran.',
    ][$u->roleKey()];
@endphp

@php $newResv = $u->hasRole('kurir') ? 0 : \App\Models\Reservation::newCount(); @endphp
@if($newResv)
    <!-- Pemberitahuan reservasi baru dari website -->
    <a href="{{ route('kasir.reservations.index') }}" class="alert d-flex align-items-center gap-2 border-0 rounded-4 text-decoration-none mb-3" style="background:#e05a6b; color:#fff">
        <i class="bi bi-bell-fill fs-4"></i>
        <span><b>{{ $newResv }} reservasi baru</b> menunggu dikonfirmasi. Ketuk untuk melihat.</span>
        <i class="bi bi-chevron-right ms-auto"></i>
    </a>
@endif

<div class="k-card mb-3">
    <h2 class="mb-1">Halo, {{ $u->name }}!</h2>
    <p class="opacity-75 mb-3">Selamat datang di Staff {{ $businessName }}. {{ $intro }}</p>

    <!-- Ringkasan hari ini -->
    <div class="row g-2">
        @isset($stats['courier'])
            <!-- Kurir: tugas miliknya -->
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:#fff3bf"><small>Tugas berjalan</small><div class="fs-4 fw-bold">{{ $stats['courier']['active'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:var(--mint)"><small>Selesai hari ini</small><div class="fs-4 fw-bold">{{ $stats['courier']['done_today'] }}</div></div></div>
        @else
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:#eef3ff"><small>Sedang dikerjakan</small><div class="fs-4 fw-bold">{{ $sedangDikerjakan }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:var(--mint)"><small>Siap diambil</small><div class="fs-4 fw-bold">{{ $queue['ready_for_pickup'] ?? 0 }}</div></div></div>
        @endisset
        @isset($stats['orders_today'])
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:#fff3bf"><small>Nota hari ini</small><div class="fs-4 fw-bold">{{ $stats['orders_today'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="p-2 rounded-3 h-100" style="background:#fdeef2"><small>Belum lunas (semua)</small><div class="fs-5 fw-bold" style="color:#e05a6b">{{ $rp($stats['unpaid_total']) }}</div></div></div>
            <!-- Uang masuk hari ini per metode bayar & kas keluar -->
            <div class="col-12">
                <div class="p-2 rounded-3 small" style="background:#fafafa; border:1px dashed var(--mint-line)">
                    <b>Uang masuk hari ini:</b>
                    @foreach(\App\Models\Order::PAYMENT_METHODS as $key => $label)
                        {{ $label }} {{ $rp($stats['cash_in_today'][$key] ?? 0) }}@if(! $loop->last) · @endif
                    @endforeach
                    · <b>Total {{ $rp($stats['cash_in_today']->sum()) }}</b>
                    &nbsp;|&nbsp; <b>Pengeluaran hari ini:</b> {{ $rp($stats['expense_today']) }}
                </div>
            </div>
        @endisset
    </div>
</div>

<div class="k-card">
    <!-- Kartu menu cepat: hanya menu milik role ini -->
    <div class="row g-3">
        @foreach($cards as [$icon, $title, $desc, $link, $bg, $roles])
            @continue(! $u->hasRole(...$roles))
            <div class="col-sm-6 col-xl-4">
                <a href="{{ $link }}" class="d-block p-3 rounded-4 text-decoration-none h-100" style="background:{{ $bg }}; color:var(--ink);">
                    <i class="bi {{ $icon }} fs-3"></i>
                    <h5 class="mb-0 mt-1">{{ $title }}</h5>
                    <small>{{ $desc }}</small>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
