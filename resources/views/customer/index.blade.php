<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Warna bilah alamat browser di HP, disamakan dengan warna navbar (pink muda) -->
    <meta name="theme-color" content="{{ \App\Support\Themes::vars(app(\App\Support\Settings::class)->themeKey())['--pink-nav'] }}">
    <title>{{ $businessName }} - Layanan Laundry Bersih, Cepat & Terpercaya</title>
    <!-- Ikon tab browser memakai logo laundry -->
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">

    <!-- Bootstrap 5 (grid, tombol, accordion) & Bootstrap Icons (ikon) dari CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font "Baloo 2" (judul) & "Nunito" (teks): huruf bulat dan ramah -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ===== 1. Palet warna & ukuran dasar (tema pastel pink & mint, ubah di sini untuk ganti tema) ===== */
        :root {
            --teal: #47a990;          /* Mint tua: tombol utama, harga, aksen (cukup gelap agar teks putih terbaca) */
            --mint-line: #a8e0d2;     /* Mint muda: garis tepi kartu & kolom isian */
            --ink: #4b3f48;           /* Warna teks utama (abu-ungu gelap) */
            --yellow: #ffd6e0;        /* Pink pastel: latar ikon & sorotan (nama variabel lama tetap dipakai di HTML) */
            --mint: #e8f8f3;          /* Latar kartu & bagian terang */
            --paper: #fdf7f9;         /* Latar halaman */
            --purple: #e68aa6;        /* Pink tua: label kecil (chip) & judul */
            --cream: #fdeef2;         /* Latar bagian kalkulator (pink sangat muda) */
            --pink: #f4a7bb;          /* Pink: aksen ikon layanan */
            --pink-nav: #f9d3dc;      /* Pink navbar & footer */
            --radius: 20px;           /* Kelengkungan sudut kartu */
            --shadow: 0 8px 22px rgba(230, 138, 166, .14); /* Bayangan lembut kemerahan */
            --bubble-img: url('{{ asset('img/gelembung.svg') }}'); /* Gambar gelembung sabun (alamat dibuat oleh Laravel) */
        }

        /* ===== 2. Gaya umum halaman ===== */
        html { scroll-behavior: smooth; scroll-padding-top: 80px; } /* Gulir halus & tidak tertutup navbar */
        /* Gelembung hiasan yang keluar sedikit dari tepi tidak membuat halaman bisa digeser ke samping di HP */
        html, body { overflow-x: clip; }
        body {
            color: var(--ink);
            font-family: "Nunito", system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 1.02rem;
            /* Latar gelembung sabun: 3 gelembung transparan yang diulang setiap 760px */
            background-color: var(--paper);
            background-image:
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.95) 0 5%, rgba(214,236,255,.35) 6% 52%, rgba(255,205,226,.35) 60%, transparent 66%),
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.95) 0 5%, rgba(205,245,232,.35) 6% 52%, rgba(226,214,255,.3) 60%, transparent 66%),
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.95) 0 6%, rgba(255,214,228,.3) 7% 52%, rgba(205,236,255,.3) 60%, transparent 66%);
            background-size: 220px 220px, 150px 150px, 300px 300px;
            background-position: -60px 140px, 92% 520px, 70% -90px;
            background-repeat: no-repeat;
        }
        h1, h2, h3, h4, h5 { font-family: "Baloo 2", "Nunito", sans-serif; font-weight: 800; line-height: 1.15; }
        section { padding: 80px 0; }
        a { color: inherit; }

        /* Label kecil berbentuk pil di atas judul bagian */
        .chip {
            display: inline-block;
            padding: 2px 14px;
            border: 2px solid var(--pink);
            border-radius: 999px;
            color: var(--purple);
            background: #fff;
            font-weight: 800;
            font-size: .82rem;
            margin-bottom: 10px;
        }
        /* Judul bagian berwarna pink */
        .section-title { font-size: clamp(1.8rem, 4vw, 2.6rem); margin-bottom: 8px; color: var(--purple); }
        .section-sub { opacity: .8; max-width: 560px; }

        /* Tombol pil: tombol utama mint (teks putih), tombol kedua putih bergaris mint */
        .btn-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 28px;
            border: 2px solid transparent;
            border-radius: 999px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 6px 16px rgba(71, 169, 144, .25);
            transition: transform .15s, box-shadow .15s, filter .15s;
        }
        /* Saat disentuh, tombol sedikit terangkat */
        .btn-pill:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-yellow { background: var(--teal); color: #fff; }
        .btn-white  { background: #fff; color: var(--teal); border-color: var(--mint-line); box-shadow: var(--shadow); }

        /* ===== 3. Navbar (pink muda) ===== */
        .site-nav {
            background: var(--pink-nav);
            box-shadow: 0 2px 12px rgba(230, 138, 166, .18);
        }
        .site-nav .navbar-brand { color: var(--purple) !important; font-family: "Baloo 2", sans-serif; }
        .site-nav .nav-link { color: var(--ink) !important; font-weight: 700; white-space: nowrap; } /* nowrap: nama menu tidak terpotong 2 baris */
        /* Warna menu bergantian pink, mint, gelap seperti contoh */
        .site-nav .nav-item:nth-child(3n+1) .nav-link { color: var(--purple) !important; }
        .site-nav .nav-item:nth-child(3n+2) .nav-link { color: var(--teal) !important; }
        .site-nav .nav-link:hover { opacity: .7; }
        .site-nav .navbar-toggler { border-color: var(--purple); }
        /* Ikon garis tiga berwarna pink (tema terang) */
        .site-nav .navbar-toggler-icon { filter: invert(55%) sepia(30%) saturate(900%) hue-rotate(300deg); }
        .brand-logo {
            width: 40px; height: 40px;
            object-fit: contain;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
            border: 2px solid var(--mint-line);
        }

        /* ===== 4. Hero (bagian pembuka) ===== */
        .hero {
            background: linear-gradient(180deg, rgba(232,248,243,.8), rgba(253,247,249,.4));
            color: var(--ink);
            padding: 60px 0 90px;
            position: relative;
            overflow: hidden;
        }
        .hero .container { position: relative; }
        .hero h1 { font-size: clamp(2.2rem, 5.5vw, 3.6rem); color: var(--purple); }
        .hero h1 span { color: var(--teal); }
        .hero p.lead { opacity: .9; max-width: 520px; }
        .hero-stat strong { display: block; font-size: 1.9rem; line-height: 1; color: var(--teal); font-family: "Baloo 2", sans-serif; }
        .hero-stat small { opacity: .8; }

        /* Ilustrasi maskot (petugas laundry + mesin cuci) dari gambar public/img/maskot-laundry.png */
        .hero-art { position: relative; width: 100%; max-width: 460px; margin: 0 auto; padding: 34px 0 40px; }
        .hero-art .maskot {
            display: block; width: 100%; height: auto; position: relative; z-index: 1;
            filter: drop-shadow(0 12px 18px rgba(230,138,166,.25));       /* Bayangan lembut di bawah maskot */
            animation: naik 6s ease-in-out infinite;
        }
        /* Gelembung sabun (gambar public/img/gelembung.svg) yang melayang naik-turun */
        .bubble {
            position: absolute;
            background: var(--bubble-img) center / contain no-repeat;
            animation: naik 5s ease-in-out infinite;
            pointer-events: none;                                        /* Tidak menghalangi klik */
        }
        /* Gelembung besar di pojok-pojok halaman, seperti contoh desain */
        .bubble-deco { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
        .bubble-deco .bubble { opacity: .9; }
        @keyframes naik { 50% { transform: translateY(-14px); } }

        /* Kartu kecil melayang di sekitar ilustrasi */
        .float-card {
            position: absolute;
            background: #fff;
            color: var(--ink);
            border: 2px solid var(--mint-line);
            border-radius: 16px;
            padding: 8px 14px;
            font-size: .85rem;
            line-height: 1.2;
            box-shadow: var(--shadow);
            animation: naik 6s ease-in-out infinite;
            z-index: 2;                                                  /* Selalu di depan gambar maskot */
        }
        .float-card i { color: var(--purple); font-size: 1.1rem; }

        /* ===== 5. Jemuran pakaian berjalan dari kanan ke kiri (di bawah hero) ===== */
        .ribbon {
            background: #fff;
            border-top: 2px solid var(--mint-line);
            border-bottom: 2px solid var(--mint-line);
            overflow: hidden;
            white-space: nowrap;
            padding: 0 0 10px;
            margin-top: -30px;
            transform: rotate(-1deg);
            position: relative;
            z-index: 2;
            box-shadow: var(--shadow);
        }
        /* Tali jemuran: garis melengkung tipis di bagian atas pita */
        .ribbon::before {
            content: "";
            position: absolute; left: -2%; right: -2%; top: 10px;
            height: 3px;
            background: #c9b6a6;
            border-radius: 3px;
        }
        /* Isi digandakan lalu digeser -50% → bergerak terus ke kiri tanpa putus */
        .ribbon-track { display: inline-block; padding-top: 10px; animation: jalan 45s linear infinite; }
        /* Satu pakaian yang digantung: bergoyang pelan dari titik jepitan (atas) */
        .cloth {
            display: inline-block;
            width: 64px; height: 64px;
            margin: 0 24px;
            position: relative;
            transform-origin: 50% 0;
            animation: goyang 2.6s ease-in-out infinite alternate;
        }
        .cloth:nth-child(even) { animation-delay: -1.3s; }        /* Selang-seling arah goyangan */
        .cloth img { width: 100%; height: 100%; display: block; }
        /* Jepitan jemuran kayu kecil di atas pakaian */
        .cloth::before {
            content: "";
            position: absolute; left: 50%; top: -7px;
            width: 7px; height: 14px;
            margin-left: -3.5px;
            background: #e8c39e;
            border: 1.5px solid #b08868;
            border-radius: 2px;
            z-index: 1;
        }
        @keyframes jalan { to { transform: translateX(-50%); } }
        @keyframes goyang { from { transform: rotate(-6deg); } to { transform: rotate(6deg); } }
        /* Pengunjung yang memilih "kurangi animasi" di HP/laptopnya: jemuran diam */
        @media (prefers-reduced-motion: reduce) {
            .ribbon-track, .cloth { animation: none; }
        }

        /* ===== 6. Kartu umum (keunggulan, langkah, dll.): putih dengan garis mint seperti contoh ===== */
        .card-soft {
            background: rgba(255,255,255,.92);
            border: 2px solid var(--mint-line);
            border-radius: var(--radius);
            padding: 26px;
            height: 100%;
            box-shadow: var(--shadow);
            transition: transform .2s;
        }
        .card-soft:hover { transform: translateY(-4px); }
        .card-soft .q { opacity: .65; font-style: italic; margin-bottom: 2px; } /* Pertanyaan pelanggan */
        .icon-round {
            width: 54px; height: 54px;
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            border: 2px solid #fff;
            box-shadow: 0 4px 10px rgba(75,63,72,.1);
            margin-bottom: 12px;
        }

        /* ===== 7. Ikon layanan berbentuk lingkaran besar ===== */
        #layanan { background: rgba(232,248,243,.75); }
        .svc-item { text-align: center; text-decoration: none; display: block; }
        .svc-circle {
            width: 104px; height: 104px;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.6rem;
            color: var(--ink);
            border: 4px solid #fff;
            box-shadow: var(--shadow), 0 0 0 2px var(--mint-line);
            transition: transform .2s;
        }
        .svc-item:hover .svc-circle { transform: translateY(-5px) rotate(-6deg); }
        .svc-item span { font-weight: 800; font-size: .95rem; }

        /* ===== 8. Daftar harga dengan tab kategori ===== */
        .tab-pills .nav-link {
            border: 2px solid var(--mint-line);
            border-radius: 999px;
            color: var(--teal);
            font-weight: 800;
            margin: 4px;
            background: #fff;
            padding: 6px 18px;
        }
        /* Tab yang sedang dipilih berwarna pink */
        .tab-pills .nav-link.active { background: var(--purple); color: #fff; border-color: var(--purple); }
        .price-board {
            background: #fff;
            border: 2px solid var(--mint-line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .price-row {
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
            padding: 16px 24px;
            border-bottom: 2px dashed rgba(168,224,210,.7);
        }
        .price-row:last-child { border-bottom: 0; }
        .price-row .price { font-size: 1.35rem; font-weight: 800; color: var(--teal); white-space: nowrap; }
        .price-row .price small { font-size: .85rem; color: var(--ink); opacity: .6; font-weight: 500; }

        /* ===== 9. Langkah cara pesan ===== */
        .step-num {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff;
            font-weight: 800;
            margin-bottom: 10px;
        }

        /* ===== 10. Lacak nota ===== */
        #lacak { background: rgba(255,255,255,.8); }
        .track-input {
            border: 2px solid var(--mint-line) !important;
            border-radius: 999px !important;
            padding: 12px 24px !important;
            font-size: 1.05rem;
        }
        /* Garis progres status cucian */
        .stepper { display: flex; justify-content: space-between; position: relative; margin: 30px 0 10px; }
        .stepper::before {
            content: ""; position: absolute; top: 22px; left: 8%; right: 8%;
            height: 4px; background: var(--mint); z-index: 0;
        }
        .stepper .pt { position: relative; z-index: 1; text-align: center; flex: 1; }
        .stepper .dot {
            width: 46px; height: 46px; border-radius: 50%;
            margin: 0 auto 6px;
            display: flex; align-items: center; justify-content: center;
            background: #fff; border: 3px solid #d8efe8; color: #a9cfc5;
            font-size: 1.15rem;
        }
        /* Tahap yang sudah lewat: mint */
        .stepper .pt.done .dot { background: var(--teal); border-color: var(--teal); color: #fff; }
        /* Tahap yang sedang berjalan: pink berdenyut lembut */
        .stepper .pt.now .dot { background: var(--purple); border-color: var(--purple); color: #fff; animation: denyut 1.6s infinite; }
        @keyframes denyut { 50% { box-shadow: 0 0 0 8px rgba(230,138,166,.3); } }
        .stepper small { font-weight: 800; font-size: .8rem; }

        /* ===== 11. Kalkulator ===== */
        #kalkulator { background: rgba(253,238,242,.85); }
        .form-control, .form-select { border: 2px solid var(--mint-line); border-radius: 14px; padding: 10px 14px; }
        .form-control:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 4px rgba(71,169,144,.18); }
        .input-group-text { border-color: var(--mint-line) !important; background: var(--mint); color: var(--teal); }
        .calc-total { font-size: 2.4rem; font-weight: 800; color: var(--teal); font-family: "Baloo 2", sans-serif; }

        /* ===== 12. Reservasi (form ke WhatsApp) ===== */
        #pesan { background: linear-gradient(135deg, rgba(232,248,243,.95), rgba(253,238,242,.95)); color: var(--ink); }
        #pesan .bi-check-circle-fill { color: var(--teal) !important; }      /* Centang daftar keunggulan */
        #pesan .form-label { font-weight: 800; }
        /* Ringkasan ongkos jemput di atas tombol kirim */
        #pesan .fee-summary { display: flex; gap: 12px; align-items: center; background: var(--mint); border: 2px dashed var(--mint-line);
                              border-radius: 16px; padding: 10px 14px; }
        #pesan .fee-summary .bi-truck { color: var(--teal); }
        #pesan .fee-summary #ringkasan-ongkos-nilai { color: var(--teal); font-size: 1.1rem; }
        #pesan .box { background: #fff; color: var(--ink); border-radius: var(--radius); padding: 28px; border: 2px solid var(--mint-line); box-shadow: var(--shadow); }
        /* Kartu alamat default di form reservasi */
        .default-address { background: var(--mint); border: 2px dashed var(--mint-line); border-radius: 16px; padding: 14px 16px; }

        /* ===== 13. FAQ (tanya jawab) ===== */
        .accordion-item { border: 2px solid var(--mint-line) !important; border-radius: 18px !important; overflow: hidden; margin-bottom: 12px; box-shadow: var(--shadow); }
        .accordion-button { font-weight: 800; font-size: 1.02rem; color: var(--ink); }
        .accordion-button:not(.collapsed) { background: var(--mint); color: var(--teal); box-shadow: none; }
        .accordion-button:focus { box-shadow: none; }

        /* ===== 14. Footer (pink muda) ===== */
        footer { background: var(--pink-nav); color: var(--ink); padding: 50px 0 30px; }
        footer h6 { color: var(--purple); }
        footer a { text-decoration: none; opacity: .85; }
        footer a:hover { opacity: 1; color: var(--teal); }

        /* ===== 15. Tombol WhatsApp melayang di pojok kanan bawah ===== */
        .wa-float {
            position: fixed; right: 18px; bottom: 18px;
            width: 58px; height: 58px; border-radius: 50%;
            background: #25d366; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            border: 3px solid #fff;
            box-shadow: 0 6px 18px rgba(37,211,102,.35);
            z-index: 1040;
            text-decoration: none;
        }

        /* ===== 16. Navigasi bawah khusus HP ===== */
        .bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff;
            border-top: 2px solid var(--pink-nav);
            box-shadow: 0 -4px 14px rgba(230,138,166,.15);
            display: flex; justify-content: space-around;
            padding: 6px 0;
            z-index: 1050;
        }
        .bottom-nav a { flex: 1; text-align: center; text-decoration: none; font-size: .75rem; font-weight: 800; color: var(--ink); opacity: .75; }
        .bottom-nav a i { display: block; font-size: 1.3rem; color: var(--purple); }
        .bottom-nav a:hover { opacity: 1; color: var(--teal); }

        /* Laptop/desktop: navigasi bawah disembunyikan */
        @media (min-width: 992px) {
            .bottom-nav { display: none !important; }
            .site-nav .navbar-collapse > .d-flex { flex-wrap: nowrap !important; } /* Tombol navbar tetap 1 baris */
            .site-nav .btn-pill { white-space: nowrap; }
        }
        /* Laptop layar sedang: menu & tombol navbar dirapatkan agar muat 1 baris */
        @media (min-width: 992px) and (max-width: 1399.98px) {
            .site-nav .nav-link { padding-left: .45rem !important; padding-right: .45rem !important; font-size: .93rem; }
            .site-nav .btn-pill { padding: 5px 12px !important; font-size: .88rem; }
            .site-nav .navbar-brand { font-size: 1.15rem !important; }
        }
        /* HP/tablet: beri ruang bawah agar konten tidak tertutup navigasi bawah */
        @media (max-width: 991.98px) {
            body { padding-bottom: 64px; }
            section { padding: 56px 0; }
            .hero { padding: 36px 0 70px; text-align: center; }
            .hero p.lead { margin-left: auto; margin-right: auto; }
            .hero .d-flex { justify-content: center; }           /* Tombol & statistik hero rata tengah di HP */
            /* Menu yang terbuka dari tombol garis tiga: kotak putih */
            .site-nav .navbar-collapse { background: #fff; border-radius: 16px; padding: 10px 14px; margin-top: 8px; box-shadow: var(--shadow); }
            .wa-float { bottom: 80px; }
            .svc-circle { width: 84px; height: 84px; font-size: 2.1rem; }
            .stepper small { font-size: .68rem; }
            .stepper .dot { width: 38px; height: 38px; font-size: 1rem; }
            .stepper::before { top: 18px; }
        }
        /* HP kecil: ilustrasi diperkecil & kartu melayang dirapatkan agar tidak keluar layar */
        @media (max-width: 575.98px) {
            .hero-art { max-width: 330px; padding: 26px 0 30px; }
            .float-card { font-size: .7rem; padding: 6px 9px; }
            .float-card small { display: none; }
            #pesan .box { padding: 20px; }
            .price-row { padding: 14px 16px; }
        }
    </style>
    {{-- Warna tema pilihan owner (menimpa warna bawaan di atas) --}}
    @include('layouts._theme-vars')
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <!-- sticky-top: navbar tetap menempel di atas saat halaman digulir -->
    <nav class="navbar navbar-expand-lg navbar-dark site-nav sticky-top py-2">
        <div class="container">
            <!-- Logo & nama usaha, diklik kembali ke beranda -->
            <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2" href="{{ route('customer.home') }}">
                <img src="{{ asset('img/logo.png') }}" alt="Logo {{ $businessName }}" class="brand-logo">
                <span>{{ $businessName }}</span>
            </a>
            <!-- Tombol menu garis tiga, hanya muncul di layar kecil -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <!-- Daftar menu: tiap link melompat ke bagian (id) di halaman ini -->
            <div class="collapse navbar-collapse" id="menuUtama">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#keunggulan">Keunggulan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#layanan">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#harga">Harga</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lacak">Lacak Nota</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kalkulator">Kalkulator</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-2 mt-lg-0 mb-2 mb-lg-0">
                    @if($currentCustomer)
                        <!-- Pelanggan sudah masuk: nama (diklik → Edit Alamat) & tombol keluar -->
                        <a href="{{ route('address.edit') }}" class="btn-pill btn-yellow py-1 px-3" title="Edit alamat">
                            <i class="bi bi-person-check-fill"></i> {{ \Illuminate\Support\Str::limit($currentCustomer->name, 16) }} <i class="bi bi-geo-alt-fill"></i>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn-pill btn-white py-1 px-3"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                        </form>
                    @else
                        <!-- Belum masuk: tombol Daftar & Masuk (verifikasi kode lewat WhatsApp) -->
                        <a href="{{ route('register.create') }}" class="btn-pill btn-yellow py-1 px-3">
                            <i class="bi bi-person-plus-fill"></i> Daftar
                        </a>
                        <a href="{{ route('login.form') }}" class="btn-pill btn-white py-1 px-3">
                            <i class="bi bi-box-arrow-in-right"></i> Masuk
                        </a>
                    @endif
                    <!-- Tombol menuju halaman kasir (staf) -->
                    <a href="{{ route('kasir.home') }}" class="btn-pill btn-white py-1 px-3">
                        <i class="bi bi-person-lock"></i> Staff
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ================= HERO (bagian pembuka) ================= -->
    <header class="hero">
        <!-- Gelembung besar dekorasi di pojok-pojok hero -->
        <div class="bubble-deco" aria-hidden="true">
            <div class="bubble" style="width:190px;height:190px;left:-60px;top:40px;"></div>
            <div class="bubble" style="width:120px;height:120px;left:8%;bottom:-40px;animation-delay:1.5s;"></div>
            <div class="bubble" style="width:150px;height:150px;right:-50px;top:-30px;animation-delay:.8s;"></div>
            <div class="bubble" style="width:90px;height:90px;right:38%;bottom:20px;animation-delay:2.4s;"></div>
            <div class="bubble" style="width:40px;height:40px;left:42%;top:30px;animation-delay:3.1s;"></div>
        </div>
        <div class="container">
            <div class="row align-items-center g-5">
                <!-- Kolom kiri: judul, deskripsi, tombol & statistik -->
                <div class="col-lg-6">
                    <span class="chip">Halo, selamat datang!</span>
                    <h1 class="mb-3">Cucian Bersih, Wangi &amp; <span>Cepat Selesai</span></h1>
                    <p class="lead mb-4">
                        Laundry kiloan dan satuan yang higienis. Bisa antar-jemput, dan status cucianmu
                        bisa dipantau langsung dari HP lewat nomor nota.
                    </p>
                    <!-- Tombol aksi utama -->
                    <div class="d-flex flex-wrap gap-3 mb-5">
                        <a href="#pesan" class="btn-pill btn-yellow"><i class="bi bi-basket3-fill"></i> Reservasi laundry</a>
                        <a href="#lacak" class="btn-pill btn-white"><i class="bi bi-search"></i> Lacak cucian</a>
                    </div>
                    <!-- Statistik diambil langsung dari data layanan di database -->
                    @php
                        $fastestHours = $allServices->min('duration_hours');    // Durasi pengerjaan tercepat (jam)
                        $cheapestPrice = $allServices->min('price');            // Harga termurah dari semua layanan
                    @endphp
                    <div class="d-flex flex-wrap gap-4">
                        <div class="hero-stat">
                            <strong>{{ $allServices->count() }}</strong>
                            <small>paket layanan</small>
                        </div>
                        @if($fastestHours)
                        <div class="hero-stat">
                            <strong>{{ $fastestHours }} jam</strong>
                            <small>pengerjaan tercepat</small>
                        </div>
                        @endif
                        @if($cheapestPrice)
                        <div class="hero-stat">
                            <strong>Rp{{ number_format($cheapestPrice, 0, ',', '.') }}</strong>
                            <small>harga mulai dari</small>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Kolom kanan: maskot petugas laundry + mesin cuci, ikut tampil di HP dalam ukuran kecil -->
                <div class="col-lg-6">
                    <div class="hero-art">
                        <img src="{{ asset('img/maskot-laundry.png') }}" alt="Petugas {{ $businessName }} dengan mesin cuci" class="maskot" width="340" height="240">
                        <!-- Gelembung sabun: posisi & ukuran diatur per gelembung -->
                        <div class="bubble" style="width:46px;height:46px;left:2%;top:38%;"></div>
                        <div class="bubble" style="width:26px;height:26px;left:30%;top:4%;animation-delay:1s;"></div>
                        <div class="bubble" style="width:58px;height:58px;right:-2%;top:6%;animation-delay:2s;"></div>
                        <div class="bubble" style="width:22px;height:22px;right:30%;bottom:6%;animation-delay:3s;"></div>
                        <!-- Kartu-kartu kecil yang melayang (di ruang kosong atas & bawah maskot, tidak menutupi karakter) -->
                        <div class="float-card" style="left:-4%;top:-2%;">
                            <i class="bi bi-droplet-half"></i> <b>Higienis</b><br><small>tidak dicampur</small>
                        </div>
                        <div class="float-card" style="right:-4%;bottom:-2%;animation-delay:1.5s;">
                            <i class="bi bi-truck"></i> <b>Antar-jemput</b><br><small>tinggal chat WA</small>
                        </div>
                        <div class="float-card" style="left:6%;bottom:-2%;animation-delay:3s;">
                            <i class="bi bi-phone"></i> <b>Lacak online</b><br><small>pakai nomor nota</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ================= JEMURAN PAKAIAN BERJALAN ================= -->
    <!-- Gambar pakaian: public/img/pakaian/*.svg. Diulang 6x supaya jemuran tetap penuh di layar lebar
         dan animasi geser (-50%) terlihat menyambung tanpa jeda -->
    @php
        $clothes = ['kaos', 'kemeja', 'dress', 'celana', 'hoodie', 'kauskaki', 'handuk', 'bajubayi'];
    @endphp
    <div class="ribbon" aria-hidden="true">
        <div class="ribbon-track">
            @for($i = 0; $i < 6; $i++)
                @foreach($clothes as $cloth)
                    <span class="cloth"><img src="{{ asset('img/pakaian/' . $cloth . '.svg') }}" alt="" width="64" height="64"></span>
                @endforeach
            @endfor
        </div>
    </div>

    <!-- ================= KEUNGGULAN ================= -->
    <section id="keunggulan">
        <div class="container">
            <div class="row align-items-end mb-4 g-3">
                <div class="col-lg-8">
                    <span class="chip">Kenapa kami?</span>
                    <h2 class="section-title">Kenapa pilih {{ $businessName }}?</h2>
                    <p class="section-sub mb-0">Pertanyaan yang paling sering ditanyakan pelanggan, kami jawab langsung.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="#pesan" class="btn-pill btn-yellow">Coba sekarang <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <!-- Kartu keunggulan: [ikon, warna ikon, pertanyaan pelanggan, judul, jawaban, lebar kolom] -->
            @php
                $features = [
                    ['bi-shield-check',          'var(--yellow)', 'Cucianku aman nggak?',        'Dicuci terpisah',        'Setiap cucian dicatat dengan nomor nota sendiri, jadi tidak tertukar dengan milik orang lain.', 'col-md-6 col-lg-4'],
                    ['bi-lightning-charge-fill', '#c8bbff',       'Aku butuh cepat, bisa?',      'Pilih paket kilat',      'Ada paket dengan waktu pengerjaan singkat. Estimasi selesai tertulis di setiap paket harga.', 'col-md-6 col-lg-4'],
                    ['bi-truck',                 'var(--pink)',   'Malas keluar rumah...',       'Antar-jemput',           'Pesan lewat WhatsApp, kurir kami jemput dan antar kembali cucianmu.', 'col-md-6 col-lg-4'],
                    ['bi-phone',                 '#9be7ee',       'Cucianku sudah sampai mana?', 'Lacak status dari HP',   'Masukkan nomor nota, langsung terlihat cucianmu sedang dicuci, dikeringkan, disetrika, atau siap diambil.', 'col-md-6 col-lg-6'],
                    ['bi-wallet2',               'var(--yellow)', 'Bayarnya ribet nggak?',       'Bisa DP, bisa lunas',    'Bayar lunas di awal atau DP dulu, sisanya dilunasi saat cucian diambil. Semua tercatat di nota.', 'col-md-12 col-lg-6'],
                ];
            @endphp
            <div class="row g-4">
                @foreach($features as [$icon, $color, $question, $title, $desc, $col])
                <div class="{{ $col }}">
                    <div class="card-soft">
                        <div class="icon-round" style="background:{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                        <p class="q">"{{ $question }}"</p>
                        <h5>{{ $title }}</h5>
                        <p class="mb-0">{{ $desc }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= LAYANAN (ikon lingkaran) ================= -->
    <section id="layanan">
        <div class="container text-center">
            <span class="chip">Layanan</span>
            <h2 class="section-title">Layanan {{ $businessName }}</h2>
            <p class="section-sub mx-auto mb-5">Dari baju harian sampai bed cover dan sepatu, semua dikerjakan dengan teliti.</p>
            <!-- Daftar layanan: [ikon Bootstrap, nama, warna lingkaran]. Ubah di sini untuk menambah/mengurangi -->
            @php
                $serviceIcons = [
                    ['bi-basket3-fill',          'Cuci Kiloan',        '#9be7ee'],
                    ['bi-wind',                  'Cuci & Setrika',     'var(--yellow)'],
                    ['bi-lightning-charge-fill', 'Paket Kilat',        '#c8bbff'],
                    ['bi-tag-fill',              'Cuci Satuan',        'var(--pink)'],
                    ['bi-backpack2-fill',        'Sepatu & Tas',       '#ffc48c'],
                    ['bi-moon-stars-fill',       'Bed Cover & Sprei',  '#9be7ee'],
                    ['bi-grid-3x3-gap-fill',     'Karpet & Gorden',    'var(--yellow)'],
                    ['bi-truck',                 'Antar-Jemput',       '#c8bbff'],
                ];
            @endphp
            <div class="row g-4 justify-content-center">
                @foreach($serviceIcons as [$icon, $label, $color])
                <div class="col-6 col-sm-4 col-md-3">
                    <!-- Klik ikon layanan langsung menuju daftar harga -->
                    <a href="#harga" class="svc-item">
                        <div class="svc-circle" style="background:{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                        <span>{{ $label }}</span>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= DAFTAR HARGA (tab per kategori, data dari database) ================= -->
    <section id="harga">
        <div class="container">
            <div class="text-center mb-4">
                <span class="chip">Harga</span>
                <h2 class="section-title">Layanan dan harga</h2>
                <p class="section-sub mx-auto">Pilih kategori untuk melihat tarifnya. Harga transparan, tanpa biaya tersembunyi.</p>
            </div>

            @if($groupedServices->isEmpty())
                <!-- Ditampilkan jika belum ada layanan aktif di database -->
                <p class="text-center opacity-75">Daftar harga belum tersedia. Silakan hubungi kami lewat WhatsApp.</p>
            @else
                <!-- Tombol tab kategori: satu tombol untuk setiap kategori layanan -->
                <ul class="nav tab-pills justify-content-center mb-4" role="tablist">
                    @foreach($groupedServices as $catTitle => $servicesList)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill"
                                data-bs-target="#cat-{{ $loop->index }}" type="button" role="tab">
                            {{ $catTitle ?: 'Lainnya' }}
                        </button>
                    </li>
                    @endforeach
                </ul>

                <!-- Isi tab: daftar layanan & harga milik kategori yang dipilih -->
                <div class="tab-content mx-auto" style="max-width: 780px;">
                    @foreach($groupedServices as $catTitle => $servicesList)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="cat-{{ $loop->index }}" role="tabpanel">
                        <div class="price-board">
                            @foreach($servicesList as $s)
                            <div class="price-row">
                                <div>
                                    <div class="fw-bold fs-5">{{ $s->name }}</div>
                                    <small class="opacity-75"><i class="bi bi-clock me-1"></i>Estimasi {{ $s->duration_hours }} jam</small>
                                </div>
                                <div class="price">
                                    Rp{{ number_format($s->price, 0, ',', '.') }}<small>/{{ $s->unit }}</small>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            <!-- Tarif antar-jemput (diatur owner di Pengaturan Usaha → Ongkos jemput) -->
            @php $tarifJemput = app(\App\Support\Settings::class)->pickupRates(); @endphp
            @if($tarifJemput)
                <div class="price-board mx-auto mt-4" style="max-width: 780px;">
                    <div class="price-row" style="background:var(--mint)"><div class="fw-bold fs-5"><i class="bi bi-truck"></i> Ongkos antar-jemput</div></div>
                    @foreach($tarifJemput as $i => $t)
                        <div class="price-row">
                            <div>{{ $i === 0 ? 'Sampai' : 'Lebih dari ' . rtrim(rtrim(number_format($tarifJemput[$i - 1]['max_km'], 1, ',', '.'), '0'), ',') . ' s.d.' }}
                                {{ rtrim(rtrim(number_format($t['max_km'], 1, ',', '.'), '0'), ',') }} km</div>
                            <div class="price">{{ $t['price'] > 0 ? 'Rp' . number_format($t['price'], 0, ',', '.') : 'Gratis' }}</div>
                        </div>
                    @endforeach
                    <div class="price-row small opacity-75">Jarak dihitung dari lokasi laundry ke titik rumah Anda (perkiraan).</div>
                </div>
            @endif

            <div class="text-center mt-4">
                <a href="#pesan" class="btn-pill btn-yellow">Pesan sekarang</a>
            </div>
        </div>
    </section>

    <!-- ================= CARA PESAN (4 langkah) ================= -->
    <section class="pt-0">
        <div class="container">
            <span class="chip">Cara pesan</span>
            <h2 class="section-title">Tinggal duduk manis, <span style="color:var(--purple)">beres!</span></h2>
            <p class="section-sub mb-4">Empat langkah, cucian beres. Kamu cukup tunggu di rumah.</p>
            <!-- Setiap langkah: [warna nomor, judul, keterangan] -->
            @php
                $steps = [
                    ['var(--teal)',   'Isi reservasi',      'Isi form reservasi di bawah atau chat WhatsApp kami.'],
                    ['var(--purple)', 'Dijemput kurir',     'Kurir datang menjemput cucian ke alamatmu.'],
                    ['#e0a800',       'Dicuci & dirapikan', 'Cucian ditimbang, dicatat di nota, lalu dikerjakan.'],
                    ['#ff6b8e',       'Diantar kembali',    'Bersih, wangi, rapi. Pantau statusnya lewat nomor nota.'],
                ];
            @endphp
            <div class="row g-4">
                @foreach($steps as $i => [$color, $title, $desc])
                <div class="col-sm-6 col-lg-3">
                    <div class="card-soft">
                        <div class="step-num" style="background:{{ $color }}">{{ $i + 1 }}</div>
                        <h5 class="mb-1">{{ $title }}</h5>
                        <p class="mb-0">{{ $desc }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= LACAK NOTA ================= -->
    <section id="lacak">
        <div class="container" style="max-width: 900px;">
            <div class="text-center mb-4">
                <span class="chip">Lacak</span>
                <h2 class="section-title">Cucianmu sudah sampai mana?</h2>
                <p class="section-sub mx-auto">
                    Masukkan nomor nota yang tertera di struk, misalnya INV-20261001-001, atau pindai QR Code di struk.
                    Status cucian hanya bisa dilihat oleh pemilik nota setelah masuk dengan nomor WhatsApp-nya.
                </p>
            </div>

            <!-- Form pencarian: dikirim (GET) ke halaman ini dengan parameter ?invoice=... -->
            <form method="GET" action="{{ route('customer.home') }}#lacak" class="d-flex flex-column flex-sm-row gap-2 mb-4"
                  onsubmit="const cur = new URLSearchParams(window.location.search).get('invoice')?.trim().toUpperCase(); const val = this.querySelector('input[name=invoice]')?.value.trim().toUpperCase(); if (cur && cur === val) { window.location.reload(); return false; }">
                <input type="text" name="invoice" class="form-control track-input"
                       placeholder="Ketik nomor nota" value="{{ $invoice }}" required>
                <button type="submit" class="btn-pill btn-yellow justify-content-center">
                    <i class="bi bi-search"></i> Lacak
                </button>
            </form>

            <!-- "Cucian saya": nota milik pelanggan yang sedang masuk, tinggal klik -->
            @if($myOrders->isNotEmpty())
                <div class="mb-4 text-center">
                    <small class="d-block opacity-75 mb-2">Cucian saya:</small>
                    <div class="d-flex flex-wrap justify-content-center gap-2" id="my-orders-list">
                        @foreach($myOrders as $mine)
                            <a href="{{ route('customer.home', ['invoice' => $mine->invoice_number]) }}#lacak"
                               data-my-order="{{ $mine->invoice_number }}"
                               onclick="if (new URLSearchParams(window.location.search).get('invoice')?.toUpperCase() === '{{ $mine->invoice_number }}') { window.location.reload(); return false; }"
                               class="btn-pill {{ $mine->invoice_number === $invoice ? 'btn-yellow' : 'btn-white' }} py-1 px-3 small">
                                {{ $mine->invoice_number }} · {{ $mine->statusLabel() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Hasil pelacakan hanya tampil jika pengunjung sudah mengetik nomor nota -->
            @if($trackState === 'login')
                <!-- Belum masuk: isi nota dirahasiakan, minta masuk dengan nomor WA pemilik nota -->
                <div class="card-soft text-center">
                    <i class="bi bi-shield-lock-fill fs-2" style="color:var(--purple)"></i>
                    <h5 class="mt-1 mb-1">Masuk dulu untuk melihat status cucian</h5>
                    <p class="mb-3">Demi keamanan, status nota <b>{{ $invoice }}</b> hanya bisa dilihat oleh pemiliknya.
                        Masuk dengan nomor WhatsApp yang tercatat di nota, setelah itu Anda langsung kembali ke sini.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="{{ route('login.form') }}" class="btn-pill btn-yellow"><i class="bi bi-box-arrow-in-right"></i> Masuk untuk melihat status</a>
                        <a href="{{ route('register.create') }}" class="btn-pill btn-white"><i class="bi bi-person-plus-fill"></i> Belum punya akun? Daftar</a>
                    </div>
                </div>
            @elseif($invoice !== '')
                @if($trackedOrder)
                    @php
                        // Ubah status pesanan menjadi nomor tahap (1-6) untuk garis progres
                        $statusMap = [
                            'received'         => 1,   // Diterima
                            'washing'          => 2,   // Dicuci
                            'drying'           => 3,   // Dikeringkan
                            'ironing'          => 4,   // Disetrika
                            'ready_for_pickup' => 5,   // Siap diambil
                            'completed'        => 6,   // Sudah diambil (semua tahap selesai)
                        ];
                        $step = $statusMap[$trackedOrder->order_status] ?? 1;

                        // Daftar tahap yang ditampilkan: [ikon, label]
                        $stages = [
                            ['bi-inbox-fill',     'Diterima'],
                            ['bi-water',          'Dicuci'],
                            ['bi-wind',           'Dikeringkan'],
                            ['bi-fire',           'Disetrika'],
                            ['bi-bag-check-fill', 'Siap Diambil'],
                        ];

                        // Label & warna status pembayaran
                        $isPaid = $trackedOrder->payment_status === 'paid';
                        $payLabel = ['paid' => 'LUNAS', 'partial' => 'DP', 'unpaid' => 'BELUM BAYAR'][$trackedOrder->payment_status]
                                    ?? strtoupper($trackedOrder->payment_status);
                        $latestLog = $trackedOrder->statusLogs->sortByDesc('id')->first();
                    @endphp
                    <div class="price-board" id="lacak-board" data-invoice="{{ $trackedOrder->invoice_number }}">
                        <!-- Kepala kartu: nomor nota, status cucian & status pembayaran -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 px-md-4" style="background:var(--mint); border-bottom:3px solid var(--ink);">
                            <div>
                                <small class="opacity-75 d-block">Nomor nota</small>
                                <h4 class="mb-0">{{ $trackedOrder->invoice_number }}</h4>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="btn-pill py-1 px-3" id="lacak-status-badge" style="background:var(--purple); color:#fff;">
                                    <i class="bi bi-arrow-repeat"></i> <span id="lacak-status-label">{{ $trackedOrder->statusLabel() }}</span>
                                </span>
                                <span class="btn-pill py-1 px-3" id="lacak-pay-badge" style="background:{{ $isPaid ? 'var(--teal)' : 'var(--yellow)' }}; color:{{ $isPaid ? '#fff' : 'var(--ink)' }};">
                                    {{ $payLabel }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3 p-md-4">
                            <!-- Garis progres: tahap sebelum $step = selesai (done), tahap = $step = sedang berjalan (now) -->
                            <div class="stepper" id="lacak-stepper">
                                @foreach($stages as $n => [$icon, $label])
                                    @php $no = $n + 1; @endphp
                                    <div class="pt {{ $step > $no ? 'done' : ($step == $no ? 'now' : '') }}" data-step-no="{{ $no }}">
                                        <div class="dot"><i class="bi {{ $icon }}"></i></div>
                                        <small>{{ $label }}</small>
                                    </div>
                                @endforeach
                            </div>
                            <div class="text-center small opacity-75 mt-2" id="lacak-updated-wrap">
                                Status cucian: <strong id="lacak-status-text">{{ $trackedOrder->statusLabel() }}</strong>
                                <span id="lacak-updated-time">
                                    @if($latestLog && $latestLog->created_at)
                                        · Diperbarui {{ \Illuminate\Support\Carbon::parse($latestLog->created_at)->format('d M Y, H:i') }} WIB
                                    @endif
                                </span>
                            </div>

                            <hr class="my-4">

                            <div class="row g-3">
                                <!-- Data pesanan: nama, waktu masuk, target selesai -->
                                <div class="col-md-6">
                                    <div class="card-soft">
                                        <small class="opacity-75">Nama pelanggan</small>
                                        <h6 class="fw-bold">{{ $trackedOrder->customer->name ?? '-' }}</h6>
                                        <small class="opacity-75">Waktu masuk</small>
                                        <h6>{{ $trackedOrder->order_date?->format('d M Y, H:i') ?? '-' }} WIB</h6>
                                        <small class="opacity-75">Target selesai</small>
                                        <h6 class="fw-bold" style="color:var(--teal)">{{ $trackedOrder->estimate_date?->format('d M Y, H:i') ?? '-' }} WIB</h6>
                                    </div>
                                </div>
                                <!-- Rincian cucian & total biaya -->
                                <div class="col-md-6">
                                    <div class="card-soft">
                                        <h6 class="fw-bold border-bottom pb-2">Rincian cucian</h6>
                                        @foreach($trackedOrder->items as $item)
                                        <div class="d-flex justify-content-between small py-1">
                                            <span>{{ $item->service->name ?? '-' }} ({{ $item->quantity }} {{ $item->service->unit ?? '' }})</span>
                                            <span class="fw-bold">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                                        </div>
                                        @endforeach
                                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                            <span class="fw-bold">Total</span>
                                            <h5 class="mb-0" style="color:var(--teal)">Rp{{ number_format($trackedOrder->final_amount, 0, ',', '.') }}</h5>
                                        </div>
                                        @if($trackedOrder->remaining() > 0)
                                        <div class="d-flex justify-content-between small pt-1">
                                            <span>Sudah dibayar</span>
                                            <span>Rp{{ number_format($trackedOrder->paid_amount, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold pt-1" style="color:#e05a6b">
                                            <span>Sisa tagihan</span>
                                            <span>Rp{{ number_format($trackedOrder->remaining(), 0, ',', '.') }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @php
                                $lacakSettings = app(\App\Support\Settings::class);
                                $lacakBanks = $trackedOrder->remaining() > 0 ? $lacakSettings->bankAccounts() : [];
                                $lacakQris = $trackedOrder->remaining() > 0 ? $lacakSettings->qrisImage() : null;
                            @endphp
                            @if($trackedOrder->remaining() > 0 && (count($lacakBanks) > 0 || $lacakQris))
                                <div class="card-soft mt-3" id="lacak-unpaid-box" style="background:#fff9db; border:2px dashed var(--ink);">
                                    <h6 class="fw-bold mb-2"><i class="bi bi-credit-card-2-front-fill" style="color:var(--teal)"></i> Pembayaran Nota (Sisa Rp{{ number_format($trackedOrder->remaining(), 0, ',', '.') }})</h6>
                                    <div class="row g-3 align-items-center">
                                        @if(count($lacakBanks) > 0)
                                            <div class="{{ $lacakQris ? 'col-md-7' : 'col-12' }}">
                                                <small class="d-block opacity-75 mb-2">Silakan transfer sesuai sisa tagihan ke salah satu rekening berikut:</small>
                                                @foreach($lacakBanks as $acc)
                                                    <div class="p-2 px-3 rounded-3 mb-2 bg-white border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                        <div>
                                                            <span class="badge rounded-pill" style="background:var(--teal); color:#fff;">{{ $acc['bank'] }}</span>
                                                            <strong class="ms-1 font-monospace fs-6">{{ $acc['number'] }}</strong>
                                                            <div class="small opacity-75">a.n. {{ $acc['holder'] }}</div>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill py-0 px-2"
                                                                onclick="navigator.clipboard?.writeText('{{ e($acc['number']) }}'); this.textContent='Tersalin!'; setTimeout(() => this.textContent='Salin', 1500);">
                                                            Salin
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($lacakQris)
                                            <div class="{{ count($lacakBanks) > 0 ? 'col-md-5' : 'col-12' }} text-center">
                                                <small class="d-block fw-bold mb-1"><i class="bi bi-qr-code-scan"></i> Pindai QRIS untuk membayar</small>
                                                <img src="{{ $lacakQris }}" alt="QRIS Pembayaran"
                                                     style="max-width:200px;width:100%;height:auto;border-radius:14px;border:2px solid var(--ink);background:#fff;padding:6px;">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Nota tidak ada ATAU milik orang lain: pesannya sengaja sama, supaya tidak membocorkan nota orang lain -->
                    <div class="card-soft text-center" style="background:#ffe3ea;">
                        <i class="bi bi-emoji-frown fs-3"></i>
                        <p class="mb-1">Nomor nota <strong>"{{ $invoice }}"</strong> tidak ditemukan di akun Anda.</p>
                        <small class="opacity-75">Periksa lagi nomornya, atau pastikan Anda masuk dengan nomor WhatsApp yang tercatat di nota.</small>
                    </div>
                @endif
            @endif
        </div>
    </section>

    <!-- ================= KALKULATOR ESTIMASI BIAYA ================= -->
    <section id="kalkulator">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <span class="chip">Kalkulator</span>
                    <h2 class="section-title">Hitung dulu, baru cuci</h2>
                    <p class="section-sub">Pilih paket dan perkiraan berat atau jumlahnya, perkiraan biaya langsung muncul. Harga final mengikuti hasil timbangan di outlet.</p>
                </div>
                <div class="col-lg-7">
                    <div class="price-board p-4">
                        <!-- Pilihan paket: value = harga per unit, data-unit = satuan (kg/pcs/m) -->
                        <label class="form-label fw-bold" for="calc-service">Paket layanan</label>
                        <select id="calc-service" class="form-select mb-3" onchange="hitungEstimasi()">
                            <option value="0" data-unit="kg">-- Pilih jenis layanan --</option>
                            @foreach($allServices as $svc)
                            <option value="{{ $svc->price }}" data-unit="{{ $svc->unit }}">
                                {{ $svc->name }} (Rp{{ number_format($svc->price, 0, ',', '.') }}/{{ $svc->unit }})
                            </option>
                            @endforeach
                        </select>

                        <!-- Input jumlah: setiap diketik, total dihitung ulang -->
                        <label class="form-label fw-bold" for="calc-qty">Perkiraan berat / jumlah</label>
                        <div class="input-group mb-3">
                            <input type="number" id="calc-qty" class="form-control" value="1" min="0.5" step="0.5" oninput="hitungEstimasi()">
                            <span class="input-group-text fw-bold" id="calc-unit-label" style="border:2px solid var(--ink); border-left:0; border-radius:0 14px 14px 0;">kg</span>
                        </div>

                        <!-- Hasil perhitungan -->
                        <div class="card-soft text-center">
                            <small class="d-block">Perkiraan total biaya</small>
                            <div class="calc-total" id="calc-total">Rp0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= RESERVASI (khusus pelanggan yang sudah masuk, diteruskan ke WhatsApp) ================= -->
    <section id="pesan">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="chip">Reservasi</span>
                    <h2 class="section-title">Reservasi laundry</h2>
                    <p class="opacity-75">Isi form ini, lalu pesananmu langsung dikirim ke WhatsApp admin kami. Alamat &amp; lokasi disimpan supaya reservasi berikutnya lebih cepat.</p>
                    <ul class="list-unstyled mt-4">
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--yellow)"></i> Jemput &amp; antar ke alamatmu</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--yellow)"></i> Nomor nota dikirim untuk lacak status</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--yellow)"></i> Bayar lunas atau DP</li>
                    </ul>
                </div>
                <div class="col-lg-7">
                    @if(! $currentCustomer)
                        <!-- Belum masuk: form dikunci, tampilkan ajakan masuk/daftar (setelah itu kembali ke sini) -->
                        <div class="box text-center">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                                 style="width:72px;height:72px;border-radius:50%;background:var(--yellow);border:3px solid var(--ink);font-size:2rem;">
                                <i class="bi bi-lock-fill"></i>
                            </div>
                            <h4 class="mb-1">Masuk dulu untuk reservasi</h4>
                            <p class="opacity-75 mb-4">Reservasi hanya untuk pelanggan yang sudah masuk dengan nomor WhatsApp, supaya data jemputanmu benar.</p>
                            <div class="d-grid gap-2">
                                <a href="{{ route('login.form', ['next' => 'reservasi']) }}" class="btn-pill btn-yellow"><i class="bi bi-box-arrow-in-right"></i> Masuk untuk reservasi</a>
                                <a href="{{ route('register.create', ['next' => 'reservasi']) }}" class="btn-pill btn-white"><i class="bi bi-person-plus-fill"></i> Belum punya akun? Daftar</a>
                            </div>
                        </div>
                    @else
                    <!-- Sudah masuk: form dikirim ke server (/reservasi), alamat & lokasi disimpan, lalu dibuka WhatsApp admin -->
                    <form class="box" method="POST" action="{{ route('reservation.store') }}" id="form-reservasi">
                        @csrf
                        <div class="row g-3">
                            <!-- Nama & nomor diambil dari akun pelanggan (tidak bisa diubah di sini) -->
                            <div class="col-md-6">
                                <label class="form-label" for="r-nama">Nama</label>
                                <input type="text" id="r-nama" class="form-control" value="{{ $currentCustomer->name }}" readonly style="background:var(--mint)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="r-hp">No. WhatsApp</label>
                                <input type="tel" id="r-hp" class="form-control" value="{{ $currentCustomer->phone }}" readonly style="background:var(--mint)">
                            </div>

                            @if($currentCustomer->hasDefaultAddress() && ! $errors->hasAny(['address', 'maps_url']))
                                <!-- Alamat default: sudah pernah diisi lengkap (alamat + lokasi), tinggal dipakai -->
                                <div class="col-12">
                                    <input type="hidden" name="use_default_address" value="1">
                                    <label class="form-label">Alamat penjemputan</label>
                                    <div class="default-address">
                                        <div class="d-flex gap-2 align-items-start">
                                            <i class="bi bi-house-heart-fill fs-4" style="color:var(--purple)"></i>
                                            <div>
                                                <span class="badge rounded-pill mb-1" style="background:var(--teal)">Alamat default</span>
                                                <div class="fw-bold">{{ $currentCustomer->address }}</div>
                                                <a href="{{ $currentCustomer->maps_url }}" target="_blank" rel="noopener" class="small" style="color:var(--teal)">
                                                    <i class="bi bi-geo-alt-fill"></i> Lihat lokasi di Google Maps
                                                </a>
                                            </div>
                                        </div>
                                        <!-- Ubah alamat → halaman Edit Alamat, lalu kembali ke form ini -->
                                        <a href="{{ route('address.edit', ['next' => 'reservasi']) }}" class="btn-pill btn-white py-1 px-3 mt-2">
                                            <i class="bi bi-pencil-square"></i> Ubah alamat
                                        </a>
                                    </div>
                                </div>
                            @else
                                <!-- Belum punya alamat default: isi alamat & lokasi (komponen bersama dengan halaman Edit Alamat) -->
                                @include('customer._location-fields', [
                                    'address'      => $currentCustomer->address,
                                    'mapsUrl'      => $currentCustomer->maps_url,
                                    'mapsRequired' => false,
                                    'showPickupFee' => true,
                                ])
                            @endif

                            <div class="col-md-6">
                                <label class="form-label" for="r-layanan">Layanan</label>
                                <!-- Pilihan layanan diambil dari database -->
                                <select id="r-layanan" name="service" class="form-select @error('service') is-invalid @enderror" required onchange="hitungHargaReservasi()">
                                    <option value="" data-price="0" data-unit="kg">-- Pilih --</option>
                                    @foreach($allServices as $svc)
                                    <option value="{{ $svc->name }}" data-price="{{ (float) $svc->price }}" data-unit="{{ $svc->unit }}" @selected(old('service') === $svc->name)>
                                        {{ $svc->name }} (Rp{{ number_format($svc->price, 0, ',', '.') }}/{{ $svc->unit }})
                                    </option>
                                    @endforeach
                                    <option value="Belum tahu, tanya admin" data-price="0" data-unit="kg" @selected(old('service') === 'Belum tahu, tanya admin')>Belum tahu, tanya admin</option>
                                </select>
                                @error('service') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="r-tanggal">Tanggal jemput</label>
                                <!-- min = hari ini, supaya tidak bisa memilih tanggal yang sudah lewat -->
                                <input type="date" id="r-tanggal" name="pickup_date" value="{{ old('pickup_date') }}" min="{{ now()->toDateString() }}" required
                                       class="form-control @error('pickup_date') is-invalid @enderror">
                                @error('pickup_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <!-- Perkiraan berat yang diisi pelanggan & perkiraan harga cucian (dihitung otomatis dari tarif layanan) -->
                            <div class="col-md-6">
                                <label class="form-label" for="r-berat">Perkiraan berat</label>
                                <div class="input-group">
                                    <input type="number" id="r-berat" name="estimated_weight" value="{{ old('estimated_weight') }}"
                                           min="0.5" max="1000" step="0.5" placeholder="Misal: 3"
                                           class="form-control @error('estimated_weight') is-invalid @enderror" oninput="hitungHargaReservasi()">
                                    <span class="input-group-text fw-bold" id="r-satuan" style="border:2px solid var(--mint-line); border-left:0; border-radius:0 14px 14px 0;">kg</span>
                                    @error('estimated_weight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="r-harga">Perkiraan harga</label>
                                <input type="text" id="r-harga" class="form-control fw-bold" value="Rp0" readonly
                                       style="background:var(--mint); color:var(--teal);">
                                <small class="opacity-75 d-block mt-1">Harga final mengikuti hasil timbangan di outlet.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="r-catatan">Catatan (opsional)</label>
                                <input type="text" id="r-catatan" name="notes" maxlength="200" value="{{ old('notes') }}" class="form-control" placeholder="Misal: ada baju luntur, minta pewangi tertentu">
                            </div>
                            <!-- Ringkasan ongkos jemput: langsung dihitung dari jarak lokasi pelanggan ke laundry -->
                            @php
                                $pickupFee = app(\App\Support\PickupFee::class);
                                $pakaiDefault = $currentCustomer->hasDefaultAddress() && ! $errors->hasAny(['address', 'maps_url']);
                                $ongkosAwal = $pakaiDefault ? \App\Support\PickupFee::summary($pickupFee->forCustomerUrl($currentCustomer->maps_url)) : null;
                            @endphp
                            <div class="col-12">
                                <div class="fee-summary">
                                    <i class="bi bi-truck fs-4"></i>
                                    <div>
                                        Ongkos jemput:
                                        <b id="ringkasan-ongkos-nilai">
                                            @if($ongkosAwal) {{ $ongkosAwal }}
                                            @elseif(! $pickupFee->isConfigured()) <span class="fw-normal opacity-75">dihitung admin</span>
                                            @elseif($pakaiDefault) <span class="fw-normal opacity-75">dihitung admin</span>
                                            @else <span class="fw-normal opacity-75">pilih titik lokasi dulu</span>
                                            @endif
                                        </b>
                                        <small class="d-block opacity-75">
                                            @if($pakaiDefault && ! $ongkosAwal && $pickupFee->isConfigured())
                                                Titik lokasi alamat default belum terbaca. <a href="{{ route('address.edit', ['next' => 'reservasi']) }}" style="color:var(--teal)">Pilih titik di peta</a> supaya ongkos langsung terhitung.
                                            @else
                                                Perkiraan dari jarak lokasi Anda ke laundry. Admin akan mengonfirmasi lewat WhatsApp.
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-pill btn-yellow w-100 justify-content-center">
                                    <i class="bi bi-whatsapp"></i> Kirim ke WhatsApp
                                </button>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FAQ (tanya jawab, buka-tutup dengan accordion Bootstrap) ================= -->
    <section id="faq">
        <div class="container" style="max-width: 820px;">
            <div class="text-center mb-4">
                <span class="chip">FAQ</span>
                <h2 class="section-title">Pertanyaan yang sering diajukan</h2>
            </div>
            <!-- Daftar tanya jawab: [pertanyaan, jawaban]. Ubah sesuai kebijakan usaha -->
            @php
                $faqs = [
                    ['Bagaimana cara melacak cucian saya?', 'Masukkan nomor nota di bagian "Lacak Nota". Status cucian (diterima, dicuci, dikeringkan, disetrika, siap diambil) akan langsung tampil.'],
                    ['Berapa lama cucian selesai?', 'Tergantung paket yang dipilih. Estimasi waktu pengerjaan tertulis di setiap paket pada bagian Harga, dan target selesai juga tercantum di nota.'],
                    ['Apakah bisa antar-jemput?', 'Bisa. Isi form reservasi atau chat WhatsApp kami, lalu kurir akan menjemput ke alamatmu.'],
                    ['Bagaimana cara pembayarannya?', 'Bisa bayar lunas di awal atau DP terlebih dahulu. Sisa tagihan dilunasi saat cucian diambil atau diantar.'],
                    ['Kenapa harga di kalkulator bisa berbeda dengan nota?', 'Kalkulator hanya perkiraan. Harga final mengikuti hasil timbangan atau jumlah barang yang dihitung di outlet.'],
                ];
            @endphp
            <div class="accordion" id="faqList">
                @foreach($faqs as $i => [$question, $answer])
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <!-- Tombol pertanyaan: diklik untuk membuka/menutup jawaban -->
                        <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq-{{ $i }}">
                            {{ $question }}
                        </button>
                    </h3>
                    <!-- Jawaban: pertanyaan pertama terbuka otomatis, yang lain tertutup -->
                    <div id="faq-{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqList">
                        <div class="accordion-body">{{ $answer }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ asset('img/logo.png') }}" alt="Logo" class="brand-logo">
                        <span class="fw-bold fs-5">{{ $businessName }}</span>
                    </div>
                    <p class="opacity-75 mb-0">Laundry bersih, wangi, rapi, dan tepat waktu.</p>
                </div>
                <div class="col-6 col-md-3">
                    <h6 class="fw-bold">Menu</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#layanan">Layanan</a></li>
                        <li><a href="#harga">Harga</a></li>
                        <li><a href="#lacak">Lacak Nota</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-4">
                    <h6 class="fw-bold">Kontak</h6>
                    <ul class="list-unstyled small">
                        <li><a href="https://wa.me/{{ config('services.laundry.wa_admin') }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> {{ \App\Support\Phone::toLocal(config('services.laundry.wa_admin')) }}</a></li>
                        <li><a href="{{ route('kasir.home') }}"><i class="bi bi-person-lock me-1"></i> Staff</a></li>
                    </ul>
                </div>
            </div>
            <hr class="opacity-25">
            <!-- Tahun hak cipta otomatis mengikuti tahun berjalan -->
            <small class="opacity-75">&copy; {{ date('Y') }} {{ $businessName }} · Sistem Informasi Manajemen Laundry &amp; Laporan Keuangan - Capstone Project Kelompok B</small>
        </div>
    </footer>

    <!-- Tombol WhatsApp melayang -->
    <a href="https://wa.me/{{ config('services.laundry.wa_admin') }}?text={{ rawurlencode('Halo ' . $businessName . ', saya mau tanya') }}" target="_blank" rel="noopener" class="wa-float" aria-label="Chat WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>

    <!-- Navigasi bawah (hanya muncul di HP) -->
    <nav class="bottom-nav">
        <a href="{{ route('customer.home') }}"><i class="bi bi-house-door-fill"></i>Beranda</a>
        <a href="#harga"><i class="bi bi-tags"></i>Harga</a>
        <a href="#lacak"><i class="bi bi-search"></i>Lacak</a>
        <a href="#kalkulator"><i class="bi bi-calculator"></i>Hitung</a>
        <a href="#pesan"><i class="bi bi-basket3"></i>Pesan</a>
    </nav>

<!-- JavaScript Bootstrap (dibutuhkan untuk menu HP, tab harga, dan accordion FAQ) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Nama laundry dari menu Kasir → Pengaturan Usaha (diubah ke teks JavaScript yang aman)
    const BUSINESS_NAME = @json($businessName);

    // Kalkulator: total = harga paket x jumlah, lalu ditampilkan dalam format Rupiah
    function hitungEstimasi() {
        const select = document.getElementById('calc-service');
        const price = parseFloat(select.value) || 0;                                         // Harga per unit dari paket terpilih
        const unit = select.options[select.selectedIndex].getAttribute('data-unit') || 'kg'; // Satuan paket terpilih
        document.getElementById('calc-unit-label').innerText = unit;                         // Tampilkan satuan di samping input

        const qty = parseFloat(document.getElementById('calc-qty').value) || 0;              // Jumlah yang diketik pengunjung
        document.getElementById('calc-total').innerText = 'Rp' + (price * qty).toLocaleString('id-ID');
    }

    // Form Reservasi: perkiraan harga = tarif layanan terpilih x perkiraan berat yang diisi pelanggan
    function hitungHargaReservasi() {
        const select = document.getElementById('r-layanan');
        const beratInput = document.getElementById('r-berat');
        const satuanEl = document.getElementById('r-satuan');
        const hargaInput = document.getElementById('r-harga');
        if (!select || !beratInput || !satuanEl || !hargaInput) return;

        const opt = select.options[select.selectedIndex];
        const price = opt ? (parseFloat(opt.getAttribute('data-price')) || 0) : 0;
        const unit = (opt && opt.getAttribute('data-unit')) || 'kg';
        satuanEl.innerText = unit;

        const qty = parseFloat(beratInput.value) || 0;
        if (opt && opt.value === 'Belum tahu, tanya admin') {
            hargaInput.value = 'Dihitung admin';
        } else {
            hargaInput.value = 'Rp' + Math.round(price * qty).toLocaleString('id-ID');
        }
    }
    hitungHargaReservasi();

    // Pembaruan status Lacak Nota & "Cucian saya" secara otomatis (real-time) saat operator mengubah status di Antrean Cucian
    @if($currentCustomer)
    (function () {
        const CHECK_URL = @json(route('customer.track.check'));
        const board = document.getElementById('lacak-board');
        const currentInv = board ? (board.getAttribute('data-invoice') || '') : @json($invoice);

        async function cekStatusLacak() {
            if (document.hidden) return;
            try {
                const url = CHECK_URL + (currentInv ? '?invoice=' + encodeURIComponent(currentInv) : '') + (currentInv ? '&' : '?') + '_t=' + Date.now();
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.authenticated) return;

                // 1. Perbarui teks status pada tombol-tombol "Cucian saya"
                if (Array.isArray(data.my_orders)) {
                    data.my_orders.forEach(function (item) {
                        const btn = document.querySelector('[data-my-order="' + item.invoice_number + '"]');
                        if (btn) btn.textContent = item.invoice_number + ' · ' + item.status_label;
                    });
                }

                // 2. Perbarui kartu Lacak Nota yang sedang dibuka
                if (data.tracked && board) {
                    const t = data.tracked;
                    const statusLabel = document.getElementById('lacak-status-label');
                    const statusText = document.getElementById('lacak-status-text');
                    const updatedTime = document.getElementById('lacak-updated-time');
                    const payBadge = document.getElementById('lacak-pay-badge');
                    const unpaidBox = document.getElementById('lacak-unpaid-box');

                    if (statusLabel) statusLabel.textContent = t.status_label;
                    if (statusText) statusText.textContent = t.status_label;
                    if (updatedTime && t.updated_at) updatedTime.textContent = ' · Diperbarui ' + t.updated_at;

                    // Perbarui bulatan garis progres (stepper)
                    document.querySelectorAll('#lacak-stepper .pt').forEach(function (pt) {
                        const no = parseInt(pt.getAttribute('data-step-no') || '0', 10);
                        pt.classList.remove('done', 'now');
                        if (t.step > no) pt.classList.add('done');
                        else if (t.step === no) pt.classList.add('now');
                    });

                    // Perbarui lencana status pembayaran
                    if (payBadge) {
                        payBadge.textContent = t.pay_label;
                        payBadge.style.background = t.is_paid ? 'var(--teal)' : 'var(--yellow)';
                        payBadge.style.color = t.is_paid ? '#fff' : 'var(--ink)';
                    }
                    if (unpaidBox && t.remaining <= 0) {
                        unpaidBox.style.display = 'none';
                    }
                }
            } catch (e) {}
        }

        setInterval(cekStatusLacak, 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) cekStatusLacak(); });
        window.addEventListener('focus', cekStatusLacak);
    })();
    @endif

</script>
</body>
</html>
