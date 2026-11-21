@extends('layouts.kasir')

@section('title', 'Kelola Staf')

@section('content')
<div class="k-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-people-fill"></i> Kelola Staf</h2>
            <p class="opacity-75 mb-0">Buat akun, beri role, ganti password, atau hapus akun staf.</p>
        </div>
        <a href="{{ route('kasir.staff.create') }}" class="btn-pill btn-yellow"><i class="bi bi-person-plus-fill"></i> Tambah staf</a>
    </div>

    <!-- Pesan error (cth: tidak bisa menghapus akun sendiri) -->
    @error('staff')
        <div class="alert alert-danger py-2">{{ $message }}</div>
    @enderror

    <!-- Keterangan hak akses tiap role -->
    <div class="row g-2 mb-3 small">
        <div class="col-sm-6 col-xl-3"><div class="p-2 rounded-3 h-100" style="background:#fdeef2"><b>Owner</b>: semua menu, laporan keuangan, kelola staf, pengaturan.</div></div>
        <div class="col-sm-6 col-xl-3"><div class="p-2 rounded-3 h-100" style="background:var(--mint)"><b>Kasir</b>: transaksi, pembayaran, pelanggan, pengeluaran, tugaskan kurir.</div></div>
        <div class="col-sm-6 col-xl-3"><div class="p-2 rounded-3 h-100" style="background:#eef3ff"><b>Operator</b>: antrean cucian &amp; ubah status pengerjaan.</div></div>
        <div class="col-sm-6 col-xl-3"><div class="p-2 rounded-3 h-100" style="background:#fff3bf"><b>Kurir</b>: tugas jemput &amp; antar cucian dengan foto bukti.</div></div>
    </div>

    <!-- Tabel staf; di HP tabel bisa digeser ke samping -->
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Nama</th><th>Email</th><th>No. HP</th><th>Role</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @foreach($staff as $s)
                    @php
                        // Warna label role
                        $badge = ['owner' => '#e68aa6', 'kasir' => '#47a990', 'operator' => '#6b8fd6', 'kurir' => '#e0a14a'][$s->roleKey()];
                    @endphp
                    <tr>
                        <td class="fw-bold">
                            {{ $s->name }}
                            @if($s->is(auth()->user())) <small class="opacity-75">(Anda)</small> @endif
                        </td>
                        <td>{{ $s->email }}</td>
                        <td>{{ $s->phone ?: '-' }}</td>
                        <td><span class="badge rounded-pill" style="background:{{ $badge }}">{{ $s->roleLabel() }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('kasir.staff.edit', $s) }}" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-pencil-square"></i> Ubah</a>
                            @unless($s->is(auth()->user()))
                                <!-- Hapus: minta konfirmasi dulu supaya tidak terhapus tanpa sengaja -->
                                <form method="POST" action="{{ route('kasir.staff.destroy', $s) }}" class="d-inline"
                                      onsubmit="return confirm('Hapus akun {{ addslashes($s->name) }}? Akun ini tidak bisa login lagi.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> Hapus</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
