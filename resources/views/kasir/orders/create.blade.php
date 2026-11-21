@extends('layouts.kasir')

@section('title', 'Nota Baru')

@section('content')
<div class="k-card">
    <a href="{{ route('kasir.orders.index') }}" class="small" style="color:var(--teal)">&larr; Kembali ke Transaksi</a>
    <h2 class="mt-2 mb-3"><i class="bi bi-plus-circle-fill"></i> Nota baru</h2>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('kasir.orders.store') }}" id="form-nota">
        @csrf

        <!-- ===== 1. Pelanggan: cari yang sudah ada, atau isi pelanggan baru ===== -->
        <h5 class="mb-2">1. Pelanggan</h5>
        <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $customer?->id) }}">
        <div id="pilih-pelanggan" @if(old('customer_id', $customer?->id)) hidden @endif>
            <div class="position-relative mb-2">
                <input type="search" id="cari-pelanggan" class="form-control" placeholder="Cari nama atau no. HP pelanggan..." autocomplete="off">
                <div id="hasil-pelanggan" class="list-group position-absolute w-100 shadow-sm" style="z-index:5"></div>
            </div>
            <div class="row g-2 p-2 rounded-3" style="background:var(--mint)">
                <small class="col-12 fw-bold">Atau pelanggan baru:</small>
                <div class="col-md-4"><input type="text" name="new_name" value="{{ old('new_name') }}" class="form-control" placeholder="Nama"></div>
                <div class="col-md-3"><input type="tel" name="new_phone" value="{{ old('new_phone') }}" class="form-control" placeholder="No. HP / WA"></div>
                <div class="col-md-5"><input type="text" name="new_address" value="{{ old('new_address') }}" class="form-control" placeholder="Alamat (opsional)"></div>
            </div>
        </div>
        <!-- Pelanggan terpilih -->
        <div id="pelanggan-terpilih" class="p-2 rounded-3 d-flex justify-content-between align-items-center" style="background:var(--mint)"
             @unless(old('customer_id', $customer?->id)) hidden @endunless>
            <div><i class="bi bi-person-check-fill" style="color:var(--teal)"></i> <b id="nama-terpilih">{{ $customer?->name }}</b> <span id="hp-terpilih" class="opacity-75">{{ $customer?->phone }}</span></div>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="ganti-pelanggan">Ganti</button>
        </div>

        <!-- ===== 2. Layanan: tambah baris sebanyak yang perlu ===== -->
        <h5 class="mt-4 mb-2">2. Layanan</h5>
        <div class="table-responsive">
            <table class="table align-middle mb-2" id="tabel-item">
                <thead><tr><th style="min-width:200px">Layanan</th><th style="width:120px">Jumlah</th><th>Catatan</th><th class="text-end">Subtotal</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <button type="button" class="btn-pill btn-white py-1 px-3" id="tambah-item"><i class="bi bi-plus-lg"></i> Tambah layanan</button>

        <!-- ===== 3. Total, diskon, pembayaran ===== -->
        <div class="row g-3 mt-3">
            <div class="col-md-6">
                <label class="form-label fw-bold" for="notes">Catatan nota (opsional)</label>
                <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Cth: ada noda di kemeja putih, jangan pakai pemutih">{{ old('notes') }}</textarea>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background:#fdeef2">
                    <div class="d-flex justify-content-between"><span>Total</span><b id="v-total">Rp0</b></div>
                    <div class="d-flex justify-content-between align-items-center my-2">
                        <label for="discount">Diskon (Rp)</label>
                        <input type="number" name="discount" id="discount" min="0" step="500" value="{{ old('discount', 0) }}" class="form-control form-control-sm text-end" style="max-width:140px">
                    </div>
                    <div class="d-flex justify-content-between fs-5"><span class="fw-bold">Bayar</span><b id="v-final" style="color:var(--teal)">Rp0</b></div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold d-block">Pembayaran awal</label>
                @foreach(['full' => 'Lunas sekarang', 'dp' => 'DP (bayar sebagian)', 'none' => 'Bayar nanti'] as $key => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pay_type" id="pay-{{ $key }}" value="{{ $key }}" @checked(old('pay_type', 'full') === $key)>
                        <label class="form-check-label" for="pay-{{ $key }}">{{ $label }}</label>
                    </div>
                @endforeach
                <div class="row g-2 mt-1" id="baris-bayar">
                    <div class="col-md-4" id="kolom-dp">
                        <input type="number" name="pay_amount" id="pay_amount" min="1" step="500" value="{{ old('pay_amount') }}" class="form-control" placeholder="Jumlah DP (Rp)">
                    </div>
                    <div class="col-md-4">
                        <select name="payment_method" class="form-select form-control">
                            @foreach(\App\Models\Order::PAYMENT_METHODS as $key => $label)
                                <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan nota</button>
            </div>
        </div>
    </form>
</div>

<!-- Contoh baris layanan (disalin JavaScript setiap klik "Tambah layanan") -->
<template id="tpl-item">
    <tr>
        <td>
            <select class="form-select form-control item-service" required>
                <option value="">-- Pilih layanan --</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" data-price="{{ (float) $s->price }}" data-unit="{{ $s->unit }}">
                        {{ $s->name }} (Rp{{ number_format((float) $s->price, 0, ',', '.') }}/{{ $s->unit }})
                    </option>
                @endforeach
            </select>
        </td>
        <td><div class="input-group input-group-sm"><input type="number" class="form-control item-qty" min="0.1" step="0.1" value="1" required><span class="input-group-text item-unit">kg</span></div></td>
        <td><input type="text" class="form-control form-control-sm item-notes" maxlength="255" placeholder="Opsional"></td>
        <td class="text-end text-nowrap item-subtotal">Rp0</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill item-hapus" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const rupiah = n => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const tbody = document.querySelector('#tabel-item tbody');
    const tpl = document.getElementById('tpl-item');

    // Beri nama input tiap baris: items[0][service_id], items[0][quantity], ... (urut ulang setiap ada perubahan)
    function namaiBaris() {
        tbody.querySelectorAll('tr').forEach((tr, i) => {
            tr.querySelector('.item-service').name = 'items[' + i + '][service_id]';
            tr.querySelector('.item-qty').name = 'items[' + i + '][quantity]';
            tr.querySelector('.item-notes').name = 'items[' + i + '][notes]';
        });
    }

    // Hitung subtotal tiap baris, total, dan jumlah bayar setelah diskon
    function hitung() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const opt = tr.querySelector('.item-service').selectedOptions[0];
            const harga = parseFloat(opt?.dataset.price || 0);
            const qty = parseFloat(tr.querySelector('.item-qty').value || 0);
            tr.querySelector('.item-unit').textContent = opt?.dataset.unit || 'kg';
            const sub = harga * qty;
            tr.querySelector('.item-subtotal').textContent = rupiah(sub);
            total += sub;
        });
        const diskon = Math.min(parseFloat(document.getElementById('discount').value || 0), total);
        document.getElementById('v-total').textContent = rupiah(total);
        document.getElementById('v-final').textContent = rupiah(total - diskon);
    }

    // Tambah 1 baris; isi = data lama (jika form tadi ditolak karena ada kesalahan)
    function tambahBaris(isi) {
        tbody.appendChild(tpl.content.cloneNode(true));
        if (isi) {
            const tr = tbody.lastElementChild;
            tr.querySelector('.item-service').value = isi.service_id || '';
            tr.querySelector('.item-qty').value = isi.quantity || 1;
            tr.querySelector('.item-notes').value = isi.notes || '';
        }
        namaiBaris();
        hitung();
    }

    tbody.addEventListener('input', hitung);
    tbody.addEventListener('change', hitung);
    tbody.addEventListener('click', e => {
        if (e.target.closest('.item-hapus') && tbody.rows.length > 1) {
            e.target.closest('tr').remove();
            namaiBaris();
            hitung();
        }
    });
    document.getElementById('tambah-item').addEventListener('click', () => tambahBaris());
    document.getElementById('discount').addEventListener('input', hitung);
    // Baris layanan dari isian sebelumnya (jika ada), kalau tidak mulai dengan 1 baris kosong
    const itemLama = Object.values(@json(old('items', [])));
    if (itemLama.length) itemLama.forEach(isi => tambahBaris(isi)); else tambahBaris();

    // Kolom jumlah DP hanya tampil jika memilih DP; metode bayar disembunyikan jika "Bayar nanti"
    function aturBayar() {
        const jenis = document.querySelector('input[name=pay_type]:checked').value;
        document.getElementById('kolom-dp').hidden = jenis !== 'dp';
        document.getElementById('baris-bayar').hidden = jenis === 'none';
    }
    document.querySelectorAll('input[name=pay_type]').forEach(r => r.addEventListener('change', aturBayar));
    aturBayar();

    // ===== Cari pelanggan (ketik minimal 2 huruf) =====
    const cari = document.getElementById('cari-pelanggan');
    const hasil = document.getElementById('hasil-pelanggan');
    let jeda;
    cari.addEventListener('input', () => {
        clearTimeout(jeda);
        const q = cari.value.trim();
        if (q.length < 2) { hasil.innerHTML = ''; return; }
        jeda = setTimeout(async () => {                       // Tunggu 300 ms setelah berhenti mengetik
            const res = await fetch(@json(route('kasir.customers.search')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            hasil.innerHTML = '';
            if (!data.length) {
                hasil.innerHTML = '<div class="list-group-item small opacity-75">Tidak ditemukan. Isi sebagai pelanggan baru di bawah.</div>';
                return;
            }
            data.forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'list-group-item list-group-item-action';
                b.textContent = c.name + ' — ' + c.phone;    // textContent: aman dari kode berbahaya
                b.addEventListener('click', () => pilih(c));
                hasil.appendChild(b);
            });
        }, 300);
    });
    function pilih(c) {
        document.getElementById('customer_id').value = c.id;
        document.getElementById('nama-terpilih').textContent = c.name;
        document.getElementById('hp-terpilih').textContent = c.phone;
        document.getElementById('pilih-pelanggan').hidden = true;
        document.getElementById('pelanggan-terpilih').hidden = false;
        hasil.innerHTML = '';
    }
    document.getElementById('ganti-pelanggan').addEventListener('click', () => {
        document.getElementById('customer_id').value = '';
        document.getElementById('pilih-pelanggan').hidden = false;
        document.getElementById('pelanggan-terpilih').hidden = true;
        cari.focus();
    });
})();
</script>
@endsection
