@extends('layouts.kasir')

@section('title', 'Pengaturan Usaha')

@section('content')
<style>
    /* Tombol lompat ke bagian pengaturan (di HP bisa digeser ke samping) */
    .set-nav { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 12px; }
    .set-nav a { white-space: nowrap; text-decoration: none; padding: 6px 14px; border-radius: 999px; background: var(--mint);
                 color: var(--ink); font-weight: 700; font-size: .9rem; }
    .set-section { scroll-margin-top: 16px; }
    /* Pilihan tema: kartu dengan contoh warna */
    .theme-card { display: block; border: 2px solid var(--mint-line); border-radius: 16px; padding: 10px; cursor: pointer; height: 100%; }
    .theme-card:has(input:checked) { border-color: var(--ink); box-shadow: 0 0 0 3px var(--mint-line); }
    .theme-card input { margin-right: 6px; }
    .swatch { display: inline-block; width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.12); }
</style>

<!-- Lompat cepat ke tiap bagian -->
<nav class="set-nav">
    <a href="#nama"><i class="bi bi-shop"></i> Nama</a>
    <a href="#lokasi"><i class="bi bi-geo-alt-fill"></i> Lokasi laundry</a>
    <a href="#ongkos"><i class="bi bi-truck"></i> Ongkos jemput</a>
    <a href="#pembayaran"><i class="bi bi-credit-card-2-front-fill"></i> Rekening &amp; QRIS</a>
    <a href="#verifikasi"><i class="bi bi-whatsapp"></i> Verifikasi WA (OTP)</a>
    <a href="#tema"><i class="bi bi-palette-fill"></i> Tema web</a>
</nav>

<!-- ===== 1. Nama laundry ===== -->
<div class="k-card mb-3 set-section" id="nama">
    <h3 class="mb-1"><i class="bi bi-shop"></i> Nama laundry</h3>
    <p class="opacity-75 mb-3">Tampil di halaman depan, halaman daftar &amp; masuk pelanggan, nota, dan pesan WhatsApp.</p>

    {{-- Form ganti nama. @@method('PUT') = Laravel memperlakukan form ini sebagai permintaan PUT (ubah data) --}}
    <form method="POST" action="{{ route('kasir.settings.update') }}">
        @csrf
        @method('PUT')
        <input type="text" id="business_name" name="business_name" maxlength="40" required
               value="{{ old('business_name', $currentName) }}"
               class="form-control form-control-lg @error('business_name') is-invalid @enderror">
        @error('business_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="opacity-75">3–40 karakter. Nama bawaan: <strong>{{ $defaultName }}</strong>.</small>

        <!-- Pratinjau isi pesan WA dengan nama yang sedang diketik -->
        <div class="p-3 rounded-4 my-3" style="background:var(--mint);">
            <small class="fw-bold d-block mb-1"><i class="bi bi-whatsapp"></i> Pratinjau di pesan verifikasi WA:</small>
            <span>KODE VERIFIKASI - <strong id="preview-name">{{ old('business_name', $currentName) }}</strong></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan nama</button>
            <button type="button" class="btn-pill btn-white" id="btn-default">Pakai nama bawaan</button>
        </div>
    </form>
</div>

<!-- ===== 2. Lokasi laundry ===== -->
<div class="k-card mb-3 set-section" id="lokasi">
    <h3 class="mb-1"><i class="bi bi-geo-alt-fill"></i> Lokasi laundry</h3>
    <p class="opacity-75 mb-3">Titik lokasi dipakai menghitung jarak &amp; ongkos jemput ke rumah pelanggan. Pilih titik tepat di depan tempat laundry.</p>

    <form method="POST" action="{{ route('kasir.settings.location') }}">
        @csrf
        @method('PUT')
        <!-- Komponen alamat + peta yang sama dengan halaman Edit Alamat pelanggan -->
        @include('customer._location-fields', [
            'address'      => $shopAddress,
            'mapsUrl'      => $shopMapsUrl,
            'mapsRequired' => true,
            'addressLabel' => 'Alamat laundry',
            'saveNote'     => 'Lokasi ini dipakai sebagai titik awal hitungan jarak jemput.',
        ])
        <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan lokasi</button>
    </form>
</div>

<!-- ===== 3. Ongkos jemput bertingkat ===== -->
<div class="k-card mb-3 set-section" id="ongkos">
    <h3 class="mb-1"><i class="bi bi-truck"></i> Ongkos jemput</h3>
    <p class="opacity-75 mb-2">
        Atur tarif menurut jarak. Contoh: <b>sampai 2 km → Rp0</b> (gratis), <b>sampai 5 km → Rp5.000</b>, <b>sampai 10 km → Rp10.000</b>.
        Pelanggan yang lebih jauh dari tarif terjauh ditandai "di luar area jemput".
    </p>
    <p class="small opacity-75 mb-3"><i class="bi bi-info-circle"></i> Jarak dihitung garis lurus dari lokasi laundry ke titik rumah pelanggan (perkiraan, jarak jalan bisa sedikit lebih jauh).</p>

    @if(! $shopMapsUrl)
        <div class="alert alert-warning py-2 small">Lokasi laundry belum diatur. Ongkos jemput baru bisa dihitung otomatis setelah lokasi disimpan.</div>
    @endif
    @if($errors->has('rates.*') || $errors->has('rates'))
        <div class="alert alert-danger py-2 small">
            @foreach($errors->get('rates.*') as $msgs) @foreach($msgs as $m) <div>{{ $m }}</div> @endforeach @endforeach
            @error('rates') <div>{{ $message }}</div> @enderror
        </div>
    @endif

    <form method="POST" action="{{ route('kasir.settings.rates') }}">
        @csrf
        @method('PUT')
        <div id="rate-rows">
            @php $rateRows = old('rates', $rates ?: [['max_km' => '', 'price' => '']]); @endphp
            @foreach($rateRows as $i => $r)
                <div class="row g-2 align-items-center mb-2 rate-row">
                    <div class="col-5 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">s.d.</span>
                            <input type="number" name="rates[{{ $i }}][max_km]" value="{{ $r['max_km'] }}" min="0.1" step="0.1" class="form-control" placeholder="5">
                            <span class="input-group-text">km</span>
                        </div>
                    </div>
                    <div class="col-5 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="rates[{{ $i }}][price]" value="{{ $r['price'] }}" min="0" step="500" class="form-control" placeholder="5000">
                        </div>
                    </div>
                    <div class="col-2 col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill rate-hapus" title="Hapus baris"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn-pill btn-white py-1 px-3" id="rate-tambah"><i class="bi bi-plus-lg"></i> Tambah tarif</button>
            <button type="submit" class="btn-pill btn-yellow py-1 px-3"><i class="bi bi-check-lg"></i> Simpan tarif</button>
        </div>
        <small class="d-block opacity-75 mt-2">Isi Rp0 untuk gratis ongkos. Kosongkan semua baris lalu simpan untuk mematikan hitungan otomatis.</small>
    </form>
</div>

<!-- ===== 4. Rekening Transfer & Gambar QRIS ===== -->
<div class="k-card mb-3 set-section" id="pembayaran">
    <h3 class="mb-1"><i class="bi bi-credit-card-2-front-fill"></i> Rekening Transfer &amp; Gambar QRIS</h3>
    <p class="opacity-75 mb-3">
        Ditampilkan otomatis pada struk cetak nota, detail nota, pesan WhatsApp, dan halaman Lacak Nota pelanggan khusus untuk <b>nota yang belum lunas (Belum Bayar / DP)</b>.
    </p>

    @if($errors->has('accounts.*') || $errors->has('accounts') || $errors->has('qris_image'))
        <div class="alert alert-danger py-2 small">
            @foreach($errors->get('accounts.*') as $msgs) @foreach($msgs as $m) <div>{{ $m }}</div> @endforeach @endforeach
            @error('accounts') <div>{{ $message }}</div> @enderror
            @error('qris_image') <div>{{ $message }}</div> @enderror
        </div>
    @endif

    <form method="POST" action="{{ route('kasir.settings.payment') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <h6 class="fw-bold mb-2"><i class="bi bi-bank"></i> Daftar Rekening Bank</h6>
        <div id="account-rows">
            @php $accRows = old('accounts', $bankAccounts ?: [['bank' => '', 'number' => '', 'holder' => '']]); @endphp
            @foreach($accRows as $i => $acc)
                <div class="row g-2 align-items-center mb-2 account-row">
                    <div class="col-4 col-md-3">
                        <input type="text" name="accounts[{{ $i }}][bank]" value="{{ $acc['bank'] ?? '' }}" maxlength="50" class="form-control form-control-sm" placeholder="Nama Bank (cth: BCA)">
                    </div>
                    <div class="col-4 col-md-4">
                        <input type="text" name="accounts[{{ $i }}][number]" value="{{ $acc['number'] ?? '' }}" maxlength="50" class="form-control form-control-sm" placeholder="No. Rekening (cth: 1234567890)">
                    </div>
                    <div class="col-3 col-md-4">
                        <input type="text" name="accounts[{{ $i }}][holder]" value="{{ $acc['holder'] ?? '' }}" maxlength="100" class="form-control form-control-sm" placeholder="Atas Nama (cth: Bersih Laundry)">
                    </div>
                    <div class="col-1 col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill account-hapus" title="Hapus rekening"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mb-3">
            <button type="button" class="btn-pill btn-white py-1 px-3" id="account-tambah"><i class="bi bi-plus-lg"></i> Tambah rekening</button>
            <small class="d-block opacity-75 mt-1">Kosongkan semua baris rekening jika tidak ingin menampilkan nomor rekening di nota.</small>
        </div>

        <hr class="my-3">

        <h6 class="fw-bold mb-2"><i class="bi bi-qr-code-scan"></i> Gambar QRIS</h6>
        <div class="row g-3 align-items-center">
            <div class="col-md-5 text-center">
                <div class="p-3 rounded-4" style="background:var(--mint); border:2px dashed var(--mint-line)">
                    @if($qrisImage)
                        <small class="d-block fw-bold mb-2">Gambar QRIS saat ini:</small>
                        <img id="qris-preview" src="{{ $qrisImage }}" alt="QRIS Pembayaran" style="max-width:180px;max-height:220px;border-radius:12px;border:2px solid #fff;background:#fff;padding:4px">
                    @else
                        <small class="d-block opacity-75" id="qris-empty-note">Belum ada gambar QRIS.</small>
                        <img id="qris-preview" alt="Pratinjau QRIS" style="max-width:180px;max-height:220px;border-radius:12px;border:2px solid #fff;background:#fff;padding:4px" hidden>
                    @endif
                </div>
            </div>
            <div class="col-md-7">
                <label class="form-label small fw-bold" for="qris_image">{{ $qrisImage ? 'Ganti gambar QRIS' : 'Unggah gambar QRIS' }}</label>
                <input type="file" id="qris_image" name="qris_image" accept="image/*" class="form-control form-control-sm mb-2">
                <small class="d-block opacity-75 mb-3">Format JPG/PNG/WebP. Gambar otomatis diperkecil agar cepat dimuat dan rapi saat dicetak di nota.</small>
                <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan Rekening &amp; QRIS</button>
            </div>
        </div>
    </form>

    @if($qrisImage)
        <form method="POST" action="{{ route('kasir.settings.qris.destroy') }}" class="mt-2" onsubmit="return confirm('Hapus gambar QRIS dari nota?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i> Hapus gambar QRIS</button>
        </form>
    @endif
</div>

<!-- ===== 5. Pengiriman Kode Verifikasi (WhatsApp OTP) ===== -->
<div class="k-card mb-3 set-section" id="verifikasi">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h3 class="mb-0"><i class="bi bi-whatsapp"></i> Pengiriman Kode Verifikasi (WhatsApp OTP)</h3>
        @if($verification['enabled'])
            <span class="badge rounded-pill" style="background:var(--teal); font-size:.8rem;"><i class="bi bi-check-circle-fill"></i> Verifikasi WA Aktif</span>
        @else
            <span class="badge rounded-pill bg-secondary" style="font-size:.8rem;"><i class="bi bi-pause-circle-fill"></i> Verifikasi WA Nonaktif</span>
        @endif
    </div>
    <p class="opacity-75 mb-3">
        Atur server pengirim kode OTP WhatsApp (Verification Hub), keterangan keperluan di pesan WA, masa berlaku kode, jeda kirim ulang, dan batas permintaan saat pelanggan Daftar atau Masuk.
    </p>

    <form method="POST" action="{{ route('kasir.settings.verification') }}" id="form-verifikasi">
        @csrf
        @method('PUT')

        <div class="p-3 rounded-4 mb-3" style="background:var(--mint);">
            <input type="hidden" name="verification_enabled" value="0">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="verification_enabled"
                       name="verification_enabled" value="1"
                       @checked(old('verification_enabled', $verification['enabled'] ? '1' : '0') == '1')>
                <label class="form-check-label fw-bold" for="verification_enabled">
                    Wajibkan pengiriman kode verifikasi 6 digit ke WhatsApp saat pelanggan Daftar &amp; Masuk
                </label>
                <small class="d-block opacity-75 mt-1">
                    Jika dimatikan sementara (misal saat server gateway WhatsApp sedang perawatan), pelanggan yang daftar/masuk akan langsung masuk tanpa menunggu kode OTP.
                </small>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold small mb-1" for="verification_hub_url">URL Endpoint Pengirim Kode (Verification Hub API)</label>
                <input type="url" id="verification_hub_url" name="verification_hub_url" maxlength="255" required
                       value="{{ old('verification_hub_url', $verification['url']) }}"
                       class="form-control @error('verification_hub_url') is-invalid @enderror"
                       placeholder="https://posaja.kantorposbekasi.com/api/kirim-verifikasi.php">
                @error('verification_hub_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="opacity-75">Alamat API server yang meneruskan kode OTP 6 digit &amp; link verifikasi otomatis ke WhatsApp pelanggan.</small>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold small mb-1" for="verification_purpose_register">Teks Keperluan Saat Daftar Pelanggan</label>
                <input type="text" id="verification_purpose_register" name="verification_purpose_register" maxlength="120" required
                       value="{{ old('verification_purpose_register', $verification['purpose_register']) }}"
                       class="form-control @error('verification_purpose_register') is-invalid @enderror">
                @error('verification_purpose_register') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="opacity-75">Tampil pada baris keperluan di pesan WhatsApp saat pendaftaran akun baru.</small>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold small mb-1" for="verification_purpose_login">Teks Keperluan Saat Masuk (Login) Pelanggan</label>
                <input type="text" id="verification_purpose_login" name="verification_purpose_login" maxlength="120" required
                       value="{{ old('verification_purpose_login', $verification['purpose_login']) }}"
                       class="form-control @error('verification_purpose_login') is-invalid @enderror">
                @error('verification_purpose_login') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="opacity-75">Tampil pada baris keperluan di pesan WhatsApp saat pelanggan lama masuk.</small>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label fw-bold small mb-1" for="verification_otp_minutes">Masa Berlaku Kode</label>
                <div class="input-group">
                    <input type="number" id="verification_otp_minutes" name="verification_otp_minutes" min="1" max="60" required
                           value="{{ old('verification_otp_minutes', $verification['otp_minutes']) }}"
                           class="form-control @error('verification_otp_minutes') is-invalid @enderror">
                    <span class="input-group-text">menit</span>
                </div>
                @error('verification_otp_minutes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="opacity-75">Bawaan: 10 menit.</small>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label fw-bold small mb-1" for="verification_resend_seconds">Jeda Kirim Ulang</label>
                <div class="input-group">
                    <input type="number" id="verification_resend_seconds" name="verification_resend_seconds" min="5" max="600" required
                           value="{{ old('verification_resend_seconds', $verification['resend_seconds']) }}"
                           class="form-control @error('verification_resend_seconds') is-invalid @enderror">
                    <span class="input-group-text">detik</span>
                </div>
                @error('verification_resend_seconds') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="opacity-75">Bawaan: 60 detik.</small>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label fw-bold small mb-1" for="verification_max_per_hour">Batas / Jam</label>
                <div class="input-group">
                    <input type="number" id="verification_max_per_hour" name="verification_max_per_hour" min="1" max="500" required
                           value="{{ old('verification_max_per_hour', $verification['max_per_hour']) }}"
                           class="form-control @error('verification_max_per_hour') is-invalid @enderror">
                    <span class="input-group-text">x</span>
                </div>
                @error('verification_max_per_hour') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="opacity-75">Maks per nomor/jam.</small>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label fw-bold small mb-1" for="verification_max_attempts">Batas Salah</label>
                <div class="input-group">
                    <input type="number" id="verification_max_attempts" name="verification_max_attempts" min="1" max="20" required
                           value="{{ old('verification_max_attempts', $verification['max_attempts']) }}"
                           class="form-control @error('verification_max_attempts') is-invalid @enderror">
                    <span class="input-group-text">x</span>
                </div>
                @error('verification_max_attempts') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="opacity-75">Sebelum kode hangus.</small>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label fw-bold small mb-1" for="verification_timeout">Timeout Hub</label>
                <div class="input-group">
                    <input type="number" id="verification_timeout" name="verification_timeout" min="3" max="60" required
                           value="{{ old('verification_timeout', $verification['timeout']) }}"
                           class="form-control @error('verification_timeout') is-invalid @enderror">
                    <span class="input-group-text">dtk</span>
                </div>
                @error('verification_timeout') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <small class="opacity-75">Batas tunggu server.</small>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Simpan pengaturan verifikasi</button>
            <button type="button" class="btn-pill btn-white" id="btn-verifikasi-default"><i class="bi bi-arrow-counterclockwise"></i> Pakai nilai bawaan</button>
        </div>
    </form>

    <hr class="my-3">

    <!-- Form Uji Coba Kirim Kode Verifikasi ke WhatsApp -->
    <form method="POST" action="{{ route('kasir.settings.verification.test') }}" class="row g-2 align-items-center">
        @csrf
        <div class="col-12">
            <label class="form-label fw-bold small mb-1" for="test_phone">
                <i class="bi bi-send-check-fill"></i> Uji Coba Kirim Kode Verifikasi ke Nomor WhatsApp
            </label>
        </div>
        <div class="col-sm-7 col-md-6">
            <input type="text" id="test_phone" name="test_phone" maxlength="20" required
                   value="{{ old('test_phone') }}"
                   class="form-control form-control-sm @error('test_phone') is-invalid @enderror"
                   placeholder="Masukkan nomor WA tujuan tes (cth: 081234567890)">
            @error('test_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-sm-5 col-md-auto">
            <button type="submit" class="btn-pill btn-white py-1 px-3">
                <i class="bi bi-whatsapp"></i> Tes Kirim Kode
            </button>
        </div>
        <div class="col-12">
            <small class="opacity-75">Mengirim 6 angka kode uji coba menggunakan pengaturan yang sudah disimpan di atas untuk memastikan gateway WhatsApp aktif.</small>
        </div>
    </form>
</div>

<!-- ===== 6. Tema web ===== -->
<div class="k-card set-section" id="tema">
    <h3 class="mb-1"><i class="bi bi-palette-fill"></i> Tema web</h3>
    <p class="opacity-75 mb-3">Warna untuk halaman depan, halaman daftar &amp; masuk, dan Staff.</p>

    <form method="POST" action="{{ route('kasir.settings.theme') }}">
        @csrf
        @method('PUT')
        <div class="row g-2 mb-3">
            @foreach(\App\Support\Themes::ALL as $key => $t)
                <div class="col-6 col-lg-4">
                    <label class="theme-card">
                        <div class="fw-bold mb-2"><input type="radio" name="theme" value="{{ $key }}" @checked($themeKey === $key)>{{ $t['name'] }}</div>
                        <!-- Contoh warna: navbar, tombol utama, judul, latar kartu -->
                        <span class="swatch" style="background:{{ $t['vars']['--pink-nav'] }}"></span>
                        <span class="swatch" style="background:{{ $t['vars']['--teal'] }}"></span>
                        <span class="swatch" style="background:{{ $t['vars']['--purple'] }}"></span>
                        <span class="swatch" style="background:{{ $t['vars']['--mint'] }}"></span>
                    </label>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn-pill btn-yellow"><i class="bi bi-check-lg"></i> Pakai tema</button>
    </form>
</div>

<script>
    // Nama: pratinjau berubah setiap kali diketik
    const nameInput = document.getElementById('business_name');
    const preview = document.getElementById('preview-name');
    nameInput.addEventListener('input', () => preview.textContent = nameInput.value || '...');
    document.getElementById('btn-default').addEventListener('click', () => {
        nameInput.value = @json($defaultName);
        nameInput.dispatchEvent(new Event('input'));
    });

    // Ongkos jemput: tambah / hapus baris tarif, lalu urutkan ulang nama input rates[0], rates[1], ...
    (function () {
        const box = document.getElementById('rate-rows');
        function namai() {
            box.querySelectorAll('.rate-row').forEach((row, i) => {
                const inputs = row.querySelectorAll('input');
                inputs[0].name = 'rates[' + i + '][max_km]';
                inputs[1].name = 'rates[' + i + '][price]';
            });
        }
        document.getElementById('rate-tambah').addEventListener('click', () => {
            const baru = box.querySelector('.rate-row').cloneNode(true);
            baru.querySelectorAll('input').forEach(inp => inp.value = '');
            box.appendChild(baru);
            namai();
        });
        box.addEventListener('click', e => {
            if (!e.target.closest('.rate-hapus')) return;
            const rows = box.querySelectorAll('.rate-row');
            if (rows.length > 1) e.target.closest('.rate-row').remove();
            else rows[0].querySelectorAll('input').forEach(inp => inp.value = ''); // Baris terakhir cukup dikosongkan
            namai();
        });
    })();

    // Rekening bank: tambah / hapus baris rekening, lalu urutkan ulang nama input accounts[0], accounts[1], ...
    (function () {
        const box = document.getElementById('account-rows');
        function namaiRekening() {
            box.querySelectorAll('.account-row').forEach((row, i) => {
                const inputs = row.querySelectorAll('input');
                inputs[0].name = 'accounts[' + i + '][bank]';
                inputs[1].name = 'accounts[' + i + '][number]';
                inputs[2].name = 'accounts[' + i + '][holder]';
            });
        }
        document.getElementById('account-tambah').addEventListener('click', () => {
            const baru = box.querySelector('.account-row').cloneNode(true);
            baru.querySelectorAll('input').forEach(inp => inp.value = '');
            box.appendChild(baru);
            namaiRekening();
        });
        box.addEventListener('click', e => {
            if (!e.target.closest('.account-hapus')) return;
            const rows = box.querySelectorAll('.account-row');
            if (rows.length > 1) e.target.closest('.account-row').remove();
            else rows[0].querySelectorAll('input').forEach(inp => inp.value = '');
            namaiRekening();
        });

        // Pratinjau & perkecil gambar QRIS di browser sebelum dikirim
        const qrisInput = document.getElementById('qris_image');
        const qrisPreview = document.getElementById('qris-preview');
        const qrisEmptyNote = document.getElementById('qris-empty-note');
        if (qrisInput) {
            qrisInput.addEventListener('change', async function () {
                const file = qrisInput.files[0];
                if (!file) return;
                if (qrisPreview) {
                    qrisPreview.src = URL.createObjectURL(file);
                    qrisPreview.hidden = false;
                }
                if (qrisEmptyNote) qrisEmptyNote.hidden = true;
                try {
                    const img = await createImageBitmap(file);
                    const skala = Math.min(1, 1024 / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * skala);
                    canvas.height = Math.round(img.height * skala);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.78));
                    if (blob) {
                        const dt = new DataTransfer();
                        dt.items.add(new File([blob], 'qris.jpg', { type: 'image/jpeg' }));
                        qrisInput.files = dt.files;
                    }
                } catch (e) {}
            });
        }
        // Reset nilai bawaan pengaturan verifikasi WA (OTP)
        const btnVerifDefault = document.getElementById('btn-verifikasi-default');
        if (btnVerifDefault) {
            btnVerifDefault.addEventListener('click', () => {
                document.getElementById('verification_enabled').checked = true;
                document.getElementById('verification_hub_url').value = @json($defaultVerificationUrl);
                document.getElementById('verification_purpose_register').value = @json(\App\Support\Settings::DEFAULTS['verification_purpose_register']);
                document.getElementById('verification_purpose_login').value = @json(\App\Support\Settings::DEFAULTS['verification_purpose_login']);
                document.getElementById('verification_otp_minutes').value = @json(\App\Support\Settings::DEFAULTS['verification_otp_minutes']);
                document.getElementById('verification_resend_seconds').value = @json(\App\Support\Settings::DEFAULTS['verification_resend_seconds']);
                document.getElementById('verification_max_per_hour').value = @json(\App\Support\Settings::DEFAULTS['verification_max_per_hour']);
                document.getElementById('verification_max_attempts').value = @json(\App\Support\Settings::DEFAULTS['verification_max_attempts']);
                document.getElementById('verification_timeout').value = @json(\App\Support\Settings::DEFAULTS['verification_timeout']);
            });
        }
    })();
</script>
@endsection
