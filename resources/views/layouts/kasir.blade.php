<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ \App\Support\Themes::vars(app(\App\Support\Settings::class)->themeKey())['--pink-nav'] }}">
    <meta name="robots" content="noindex">
    {{-- noindex: halaman kasir tidak dimunculkan di Google --}}
    <title>@yield('title', 'Staff') - {{ $businessName }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Tema pastel pink & mint, sama dengan halaman depan */
        :root { --teal:#47a990; --mint-line:#a8e0d2; --ink:#4b3f48; --yellow:#ffd6e0; --mint:#e8f8f3; --paper:#fdf7f9;
                --purple:#e68aa6; --pink-nav:#f9d3dc; --shadow:0 8px 22px rgba(230,138,166,.14); }
        body { background: var(--paper); color: var(--ink); font-family: "Nunito", system-ui, sans-serif; font-size: 1.02rem; }
        h1, h2, h3, h4, h5 { font-family: "Baloo 2", "Nunito", sans-serif; font-weight: 800; color: var(--purple); }

        /* Bilah atas pink muda: nama laundry + nama staf + tombol keluar */
        .kasir-top { background: var(--pink-nav); color: var(--ink); box-shadow: 0 2px 12px rgba(230,138,166,.18); }
        .kasir-top .brand { color: var(--purple); text-decoration: none; font-family: "Baloo 2", sans-serif; font-weight: 800; font-size: 1.2rem; }
        .kasir-top .brand img { width: 34px; height: 34px; background: #fff; border-radius: 50%; padding: 2px; object-fit: contain; }
        .kasir-top .badge { background: var(--teal) !important; color: #fff !important; }
        .kasir-top .btn-outline-light { color: var(--teal); border-color: var(--teal); background: #fff; font-weight: 700; }

        /* Menu samping (di HP menjadi baris menu yang bisa digeser) */
        .kasir-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 14px;
                        color: var(--ink); text-decoration: none; font-weight: 700; white-space: nowrap; }
        .kasir-menu a i { color: var(--teal); }
        .kasir-menu a:hover { background: var(--mint); }
        .kasir-menu a.active { background: var(--purple); color: #fff; }
        .kasir-menu a.active i { color: #fff; }
        .kasir-menu a.disabled { opacity: .45; pointer-events: none; }
        /* Lencana jumlah reservasi baru di menu */
        .resv-badge { background: #e05a6b; color: #fff; border-radius: 999px; font-size: .72rem; padding: 1px 8px; margin-left: auto; }
        .kasir-menu a.active .resv-badge { background: #fff; color: #e05a6b; }

        /* Kartu isi: putih bergaris mint */
        .k-card { background: #fff; border: 2px solid var(--mint-line); border-radius: 20px; box-shadow: var(--shadow); padding: 24px; }
        .form-control { border: 2px solid var(--mint-line); border-radius: 14px; padding: 10px 14px; }
        .form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 4px rgba(71,169,144,.18); }
        .btn-pill { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 24px;
                    border: 2px solid transparent; border-radius: 999px; font-weight: 800; text-decoration: none;
                    color: var(--ink); box-shadow: 0 6px 16px rgba(71,169,144,.22); transition: transform .15s, filter .15s; }
        .btn-pill:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-yellow { background: var(--teal); color: #fff; }
        .btn-white  { background: #fff; color: var(--teal); border-color: var(--mint-line); }

        @media (max-width: 767.98px) {
            .kasir-menu { display: flex; overflow-x: auto; gap: 6px; padding-bottom: 6px; }
            .k-card { padding: 18px; }
        }
    </style>
    {{-- Warna tema pilihan owner (menimpa warna bawaan di atas) --}}
    @include('layouts._theme-vars')
</head>
<body>
    <!-- Bilah atas -->
    <header class="kasir-top py-2">
        <div class="container-xl d-flex align-items-center justify-content-between gap-2">
            <a href="{{ route('kasir.home') }}" class="brand d-flex align-items-center gap-2">
                <img src="{{ asset('img/logo.png') }}" alt="Logo"> {{ $businessName }} <span class="badge bg-warning text-dark ms-1">Staff</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="d-none d-sm-inline opacity-75"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
                <!-- Keluar: POST + CSRF -->
                <form method="POST" action="{{ route('kasir.logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light rounded-pill"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <div class="container-xl py-4">
        <div class="row g-4">
            <!-- Menu Kasir: hanya menu yang boleh dibuka role akun ini yang ditampilkan.
                 (Halaman yang tidak berhak juga dikunci di server lewat middleware role.)
                 request()->routeIs(...) menandai menu yang sedang dibuka -->
            @php
                $u = auth()->user();
                $isKurir = $u->hasRole('kurir');
                // Lencana: kurir = jumlah tugas aktif miliknya; staf lain = reservasi baru dari website
                $badgeCount = $isKurir ? \App\Models\CourierTask::activeCountFor($u->id) : \App\Models\Reservation::newCount();
            @endphp
            <aside class="col-md-3">
                <nav class="kasir-menu d-md-grid gap-1">
                    <a href="{{ route('kasir.home') }}" class="{{ request()->routeIs('kasir.home') ? 'active' : '' }}"><i class="bi bi-grid-fill"></i> Dashboard</a>

                    @if($isKurir)
                        {{-- Kurir: tugas jemput & antar miliknya + lencana jumlah tugas berjalan --}}
                        <a href="{{ route('kasir.courier.index') }}" class="{{ request()->routeIs('kasir.courier.*') ? 'active' : '' }}">
                            <i class="bi bi-truck"></i> Tugas Saya
                            <span class="resv-badge {{ $badgeCount ? '' : 'd-none' }}">{{ $badgeCount }}</span>
                        </a>
                    @else
                        {{-- Owner, kasir, operator: reservasi jemput dari website + lencana jumlah yang baru --}}
                        <a href="{{ route('kasir.reservations.index') }}" class="{{ request()->routeIs('kasir.reservations.*') ? 'active' : '' }}">
                            <i class="bi bi-bell-fill"></i> Reservasi
                            <span class="resv-badge {{ $badgeCount ? '' : 'd-none' }}">{{ $badgeCount }}</span>
                        </a>
                    @endif

                    {{-- Owner & kasir: melayani pelanggan dan pembayaran --}}
                    @if($u->hasRole('owner', 'kasir'))
                        <a href="{{ route('kasir.orders.index') }}" class="{{ request()->routeIs('kasir.orders.*') ? 'active' : '' }}"><i class="bi bi-receipt"></i> Transaksi</a>
                    @endif
                    {{-- Owner, kasir, operator: antrean cucian & ubah status pengerjaan --}}
                    @unless($isKurir)
                        <a href="{{ route('kasir.queue.index') }}" class="{{ request()->routeIs('kasir.queue.*') ? 'active' : '' }}"><i class="bi bi-basket3-fill"></i> Antrean Cucian</a>
                    @endunless
                    {{-- Owner & kasir: tugas kurir, data pelanggan & kas keluar --}}
                    @if($u->hasRole('owner', 'kasir'))
                        <a href="{{ route('kasir.courier-tasks.index') }}" class="{{ request()->routeIs('kasir.courier-tasks.*') ? 'active' : '' }}"><i class="bi bi-truck"></i> Tugas Kurir</a>
                        <a href="{{ route('kasir.customers.index') }}" class="{{ request()->routeIs('kasir.customers.*') ? 'active' : '' }}"><i class="bi bi-person-lines-fill"></i> Pelanggan</a>
                        <a href="{{ route('kasir.expenses.index') }}" class="{{ request()->routeIs('kasir.expenses.*') ? 'active' : '' }}"><i class="bi bi-wallet2"></i> Pengeluaran</a>
                    @endif

                    {{-- Khusus owner --}}
                    @if($u->isOwner())
                        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="bi bi-graph-up-arrow"></i> Laporan Keuangan</a>
                        <a href="{{ route('kasir.services.index') }}" class="{{ request()->routeIs('kasir.services.*') ? 'active' : '' }}"><i class="bi bi-tags-fill"></i> Layanan &amp; Harga</a>
                        <a href="{{ route('kasir.staff.index') }}" class="{{ request()->routeIs('kasir.staff.*') ? 'active' : '' }}"><i class="bi bi-people-fill"></i> Kelola Staf</a>
                        <a href="{{ route('kasir.activity-logs.index') }}" class="{{ request()->routeIs('kasir.activity-logs.*') ? 'active' : '' }}"><i class="bi bi-clock-history"></i> Log Aktivitas</a>
                        <a href="{{ route('kasir.courier-photos.index') }}" class="{{ request()->routeIs('kasir.courier-photos.*') ? 'active' : '' }}"><i class="bi bi-images"></i> Foto Bukti</a>
                        <a href="{{ route('kasir.settings') }}" class="{{ request()->routeIs('kasir.settings') ? 'active' : '' }}"><i class="bi bi-gear-fill"></i> Pengaturan Usaha</a>
                    @endif

                    <a href="{{ route('customer.home') }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Lihat Website</a>
                </nav>
            </aside>

            <!-- Isi halaman -->
            <main class="col-md-9">
                @if(session('status'))
                    <div class="alert alert-success border-2 rounded-4">{{ session('status') }}</div>
                @endif
                {{-- Setelah menugaskan kurir: beri tahu kurir lewat WhatsApp (pesan tugas sudah terisi, kasir tinggal menekan Kirim) --}}
                @if($wa = session('courier_wa'))
                    <div class="alert border-0 rounded-4 d-flex flex-wrap align-items-center gap-2" style="background:#e7f8ee" id="wa-kurir">
                        <i class="bi bi-whatsapp fs-3" style="color:#25d366"></i>
                        @if($wa['link'])
                            <span class="flex-grow-1">Beri tahu <b>{{ $wa['name'] }}</b> lewat WhatsApp: pesan tugas sudah terisi, tinggal tekan <b>Kirim</b>.</span>
                            <a href="{{ $wa['link'] }}" target="_blank" rel="noopener" class="btn btn-success rounded-pill fw-bold px-3">
                                <i class="bi bi-send-fill"></i> Kirim WA ke kurir
                            </a>
                        @else
                            <span class="flex-grow-1">No. HP <b>{{ $wa['name'] }}</b> belum diisi, jadi pemberitahuan WA tidak bisa dikirim.
                                @if(auth()->user()->isOwner()) Isi di <a href="{{ route('kasir.staff.index') }}">Kelola Staf</a>. @else Minta owner mengisinya di Kelola Staf. @endif
                                Kurir tetap melihat tugas di menu Tugas Saya.</span>
                        @endif
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Notifikasi reservasi baru: muncul di pojok layar + bunyi + notifikasi browser (jika diizinkan) -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080; max-width:100%">
        <div id="resv-toast" class="toast bg-white border-0 rounded-4 shadow" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="toast-header text-white rounded-top-4" style="background:#e05a6b">
                <i class="bi bi-bell-fill me-2"></i><strong class="me-auto">{{ $isKurir ? 'Tugas baru!' : 'Reservasi baru!' }}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
            <div class="toast-body">
                <div id="resv-toast-text"></div>
                <a href="{{ $isKurir ? route('kasir.courier.index') : route('kasir.reservations.index') }}" class="btn btn-sm text-white rounded-pill mt-2" style="background:var(--teal)">{{ $isKurir ? 'Lihat tugas' : 'Lihat reservasi' }}</a>
            </div>
        </div>
    </div>
    <script>
    (function () {
        // Kurir mengecek tugas baru miliknya; staf lain mengecek reservasi baru dari website
        const URL_CEK = @json($isKurir ? route('kasir.courier.check') : route('kasir.reservations.check'));
        const URL_LIST = @json($isKurir ? route('kasir.courier.index') : route('kasir.reservations.index'));
        const KUNCI = @json($isKurir ? 'kurirLastId' : 'resvLastId');
        const JUDUL_NOTIF = @json($isKurir ? 'Tugas baru' : 'Reservasi baru');
        const judulAsli = document.title.replace(/^\(\d+\)\s*/, '');
        // id reservasi baru terakhir yang sudah diberitahukan (disimpan di browser supaya tidak berbunyi berulang)
        let lastId = parseInt(localStorage.getItem(KUNCI) || '0', 10);

        function perbaruiLencana(n) {
            document.querySelectorAll('.resv-badge').forEach(el => { el.textContent = n; el.classList.toggle('d-none', !n); });
            document.title = (n ? '(' + n + ') ' : '') + judulAsli;
        }

        // Bunyi "ting-tung" pendek tanpa file suara (Web Audio). Browser bisa menahan bunyi sebelum halaman pernah diklik.
        function bunyi() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [[880, 0], [660, 0.22]].forEach(([f, t]) => {
                    const o = ctx.createOscillator(), g = ctx.createGain();
                    o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
                    g.gain.setValueAtTime(0.25, ctx.currentTime + t);
                    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.2);
                    o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.2);
                });
            } catch (e) {}
        }

        function beriTahu(r, n) {
            // Kurir: "Budi · Antar · 07-11-2026"; staf lain: "Budi · Cuci Setrika · jemput 07-11-2026"
            const teks = r.name + ' · ' + r.service + (KUNCI === 'kurirLastId' ? ' · ' : ' · jemput ') + r.pickup_date
                + (n > 1 ? ' (total ' + n + ' ' + JUDUL_NOTIF.toLowerCase() + ')' : '');
            document.getElementById('resv-toast-text').textContent = teks;
            bootstrap.Toast.getOrCreateInstance(document.getElementById('resv-toast')).show();
            bunyi();
            if ('Notification' in window && Notification.permission === 'granted') {
                const notif = new Notification(JUDUL_NOTIF, { body: teks, tag: KUNCI, icon: @json(asset('img/logo.png')) });
                notif.onclick = () => { window.focus(); location.href = URL_LIST; };
            }
        }

        async function cek() {
            try {
                const res = await fetch(URL_CEK, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                perbaruiLencana(data.count);
                if (data.latest && data.latest.id > lastId) {
                    // Pertama kali dibuka di browser ini: cukup dicatat tanpa bunyi (lencana sudah menunjukkan jumlahnya)
                    if (lastId > 0) beriTahu(data.latest, data.count);
                    lastId = data.latest.id;
                    localStorage.setItem(KUNCI, String(lastId));
                }
            } catch (e) { /* Koneksi putus / sesi habis: dicoba lagi di putaran berikutnya */ }
        }

        cek();
        setInterval(cek, 30000);                                   // Cek tiap 30 detik
        document.addEventListener('visibilitychange', () => { if (!document.hidden) cek(); });

        // Tombol "Aktifkan notifikasi" (di halaman Reservasi): minta izin notifikasi browser
        const izin = document.getElementById('notif-izin');
        if (izin && 'Notification' in window && Notification.permission === 'default') {
            izin.classList.remove('d-none');
            izin.addEventListener('click', () => Notification.requestPermission().then(p => {
                if (p !== 'default') izin.classList.add('d-none');
                if (p === 'granted') bunyi();                       // Sekalian uji bunyi
            }));
        }
    })();
    </script>
</body>
</html>
