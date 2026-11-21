{{-- Tab pilihan Daftar / Masuk di atas kartu. Dipakai dengan: include('register._tabs', ['active' => 'daftar' atau 'masuk']) --}}
<style>
    .auth-tabs { display: flex; background: var(--mint); border: 2px solid var(--mint-line); border-radius: 999px; padding: 4px; margin-bottom: 22px; }
    .auth-tabs a { flex: 1; text-align: center; padding: 8px 0; border-radius: 999px; font-weight: 800; text-decoration: none; color: var(--teal); }
    /* Tab yang sedang dibuka: pink dengan teks putih */
    .auth-tabs a.active { background: var(--purple); color: #fff; box-shadow: 0 4px 12px rgba(230,138,166,.3); }
</style>
<nav class="auth-tabs">
    <a href="{{ route('register.create') }}" class="{{ $active === 'daftar' ? 'active' : '' }}"><i class="bi bi-person-plus-fill"></i> Daftar</a>
    <a href="{{ route('login.form') }}" class="{{ $active === 'masuk' ? 'active' : '' }}"><i class="bi bi-box-arrow-in-right"></i> Masuk</a>
</nav>

<!-- Keterangan alasan diarahkan ke sini (cth: harus masuk dulu untuk reservasi) -->
@if(session('info') || session('after_login'))
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle-fill"></i> {{ session('info') ?? 'Masuk atau daftar dulu, setelah itu Anda langsung kembali ke form reservasi.' }}
    </div>
@endif
