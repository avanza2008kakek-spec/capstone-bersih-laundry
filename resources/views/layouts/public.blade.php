<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ \App\Support\Themes::vars(app(\App\Support\Settings::class)->themeKey())['--pink-nav'] }}">
    {{-- Judul tab: diisi oleh halaman anak lewat @@section('title') (komentar Blade, tidak dijalankan) --}}
    <title>@yield('title', $businessName)</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">

    <!-- Bootstrap, ikon, dan font yang sama dengan halaman depan -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Palet warna tema pastel pink & mint, sama dengan halaman depan */
        :root { --teal:#47a990; --mint-line:#a8e0d2; --ink:#4b3f48; --yellow:#ffd6e0; --mint:#e8f8f3; --paper:#fdf7f9;
                --purple:#e68aa6; --pink:#f4a7bb; --pink-nav:#f9d3dc; --shadow:0 8px 22px rgba(230,138,166,.14); }

        body {
            min-height: 100vh;
            color: var(--ink);
            font-family: "Nunito", system-ui, sans-serif;
            font-size: 1.02rem;
            /* Latar mint-pink lembut dengan gelembung sabun transparan */
            background-color: var(--paper);
            background-image:
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.95) 0 5%, rgba(214,236,255,.4) 6% 52%, rgba(255,205,226,.4) 60%, transparent 66%),
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.95) 0 5%, rgba(205,245,232,.4) 6% 52%, rgba(226,214,255,.35) 60%, transparent 66%),
                linear-gradient(160deg, #e8f8f3 0%, #fdf7f9 55%, #fdeef2 100%);
            background-size: 240px 240px, 160px 160px, 100% 100%;
            background-position: -50px 60px, 92% 75%, 0 0;
            background-repeat: no-repeat;
        }
        h1, h2, h3, h4 { font-family: "Baloo 2", "Nunito", sans-serif; font-weight: 800; color: var(--purple); }

        /* Logo + nama usaha di atas kartu */
        .brand { color: var(--purple); text-decoration: none; font-family: "Baloo 2", sans-serif; font-weight: 800; font-size: 1.4rem; }
        .brand img { width: 44px; height: 44px; background: #fff; border-radius: 50%; padding: 3px; object-fit: contain; border: 2px solid var(--mint-line); }

        /* Kartu putih utama dengan garis mint & bayangan lembut */
        .auth-card {
            background: #fff;
            border: 2px solid var(--mint-line);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 32px;
        }
        .chip { display:inline-block; padding:2px 14px; border:2px solid var(--pink); border-radius:999px; color:var(--purple); font-weight:800; font-size:.82rem; margin-bottom:8px; }

        /* Input & tombol */
        .form-control { border: 2px solid var(--mint-line); border-radius: 14px; padding: 10px 14px; font-size: 1.02rem; }
        .form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 4px rgba(71,169,144,.18); }
        .input-group-text { border: 2px solid var(--mint-line); border-radius: 14px; font-weight: 700; background: var(--mint); color: var(--teal); }
        .btn-pill {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px 26px;
            border: 2px solid transparent; border-radius: 999px;
            font-weight: 800; text-decoration: none; color: var(--ink);
            box-shadow: 0 6px 16px rgba(71,169,144,.22);
            transition: transform .15s, filter .15s;
        }
        .btn-pill:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-pill:disabled { opacity: .55; transform: none; }
        .btn-yellow { background: var(--teal); color: #fff; }            /* Tombol utama: mint, teks putih */
        .btn-white  { background: #fff; color: var(--teal); border-color: var(--mint-line); }
        .link-plain { background: none; border: 0; color: var(--teal); font-weight: 800; text-decoration: underline; padding: 0; }
        .back-link { color: var(--teal); font-weight: 700; }
        @media (max-width: 575.98px) { .auth-card { padding: 22px; } } /* HP kecil: kartu lebih rapat */
    </style>
    {{-- Warna tema pilihan owner (menimpa warna bawaan di atas) --}}
    @include('layouts._theme-vars')
    @stack('styles')
</head>
<body class="d-flex flex-column align-items-center justify-content-center py-5 px-3">

    <!-- Logo, diklik kembali ke beranda -->
    <a href="{{ route('customer.home') }}" class="brand d-flex align-items-center gap-2 mb-4">
        <img src="{{ asset('img/logo.png') }}" alt="Logo"> {{ $businessName }}
    </a>

    <!-- Isi halaman (form daftar / verifikasi / berhasil) -->
    <main class="w-100" style="max-width: 460px;">
        @yield('content')
    </main>

    <a href="{{ route('customer.home') }}" class="back-link mt-4 small">&larr; Kembali ke beranda</a>

    @stack('scripts')
</body>
</html>
