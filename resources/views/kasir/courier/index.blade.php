@extends('layouts.kasir')

@section('title', 'Tugas Saya')

@section('content')
@php
    use App\Http\Controllers\Kasir\ReservationController as R;
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
    $active = $active->values();
    // Data peta: tugas berjalan yang punya titik koordinat (nomor urut sama dengan nomor di kartu)
    $titik = $active->map(fn ($t, $i) => ($c = $t->coords()) ? [
        'id' => $t->id, 'no' => $i + 1, 'lat' => $c[0], 'lng' => $c[1], 'name' => $t->customer?->name ?? '-',
        'type' => $t->typeLabel(), 'address' => $t->address, 'nav' => $t->routeUrl(),
    ] : null)->filter()->values();
    // Rute semua tugas sekaligus di Google Maps (urut sesuai daftar, maks. 10 titik)
    $semua = $titik->take(10);
    $ruteSemua = $semua->count() >= 2
        ? 'https://www.google.com/maps/dir/?api=1&travelmode=two-wheeler&destination=' . $semua->last()['lat'] . ',' . $semua->last()['lng']
          . '&waypoints=' . rawurlencode($semua->slice(0, -1)->map(fn ($p) => $p['lat'] . ',' . $p['lng'])->implode('|'))
        : null;
@endphp
<style>
    /* Halaman kurir: dirancang untuk HP (tombol besar, satu kolom) */
    .job { border: 2px solid var(--mint-line); border-radius: 18px; padding: 14px; margin-bottom: 14px; background: #fff; }
    .job.on-the-way { border-color: #6b8fd6; }
    .job dl { display: grid; grid-template-columns: auto 1fr; gap: 3px 10px; margin: 10px 0; }
    .job dt { font-weight: 600; opacity: .7; }
    .job dd { margin: 0; overflow-wrap: anywhere; }
    .job .type-pill { font-size: .8rem; padding: 3px 12px; border-radius: 999px; color: #fff; font-weight: 800; }
    .job .actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .job .actions .btn { border-radius: 14px; font-weight: 700; padding: 10px 4px; font-size: .9rem; }
    .job .no { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%;
               background: var(--purple); color: #fff; font-weight: 800; font-size: .9rem; margin-right: 6px; vertical-align: middle; }
    .btn-nav { border-radius: 14px; font-weight: 800; padding: 12px; font-size: 1.05rem; }
    .collect { background: #fff3bf; border: 2px dashed #f2c94c; border-radius: 14px; padding: 10px 12px; }
    .photo-box { border: 2px dashed var(--mint-line); border-radius: 14px; padding: 12px; text-align: center; background: var(--mint); }
    .photo-box img { max-width: 100%; max-height: 260px; border-radius: 12px; margin-top: 8px; }
    .photo-box label.btn { border-radius: 14px; font-weight: 800; }
    .history { font-size: .9rem; border-bottom: 1px dashed var(--mint-line); padding: 8px 0; }
    /* Peta tujuan */
    #peta-kurir { height: 320px; border-radius: 16px; overflow: hidden; border: 2px solid var(--mint-line); z-index: 0; background: var(--mint); }
    @media (max-width: 575.98px) { #peta-kurir { height: 260px; } }
    .pin-no { background: var(--purple); color: #fff; border: 2px solid #fff; border-radius: 50%; display: flex !important;
              align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 2px 6px rgba(0,0,0,.35); }
    .pin-me { background: #2a78ff; border: 3px solid #fff; border-radius: 50%; box-shadow: 0 0 0 6px rgba(42,120,255,.25); }
</style>

<div class="k-card mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h2 class="mb-0"><i class="bi bi-truck"></i> Tugas Saya</h2>
        <!-- Izin notifikasi browser: tugas baru tetap muncul walau HP sedang membuka aplikasi lain (browser tetap berjalan) -->
        <button type="button" id="notif-izin" class="btn btn-sm btn-outline-secondary rounded-pill d-none"><i class="bi bi-bell"></i> Aktifkan notifikasi</button>
    </div>
    <p class="opacity-75 mb-0">Tugas jemput &amp; antar dari kasir. Setiap tugas wajib <b>difoto</b> saat cucian dijemput atau diserahkan.</p>
</div>

@if($active->isNotEmpty())
    <!-- Peta tujuan semua tugas + posisi kurir (OpenStreetMap, gratis). Navigasi belok-demi-belok dibuka di Google Maps. -->
    <div class="k-card mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0"><i class="bi bi-map-fill"></i> Peta tujuan</h5>
            @if($titik->isNotEmpty())
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btn-lokasi-saya"><i class="bi bi-crosshair"></i> Lokasi saya</button>
            @endif
        </div>
        @if($titik->isEmpty())
            <p class="small opacity-75 mb-0">Tugas belum punya titik lokasi di peta. Gunakan tombol <b>Navigasi ke tujuan</b> di kartu (memakai alamat).</p>
        @else
            <div id="peta-kurir" aria-label="Peta tujuan tugas"></div>
            <small class="d-block opacity-75 mt-1" id="peta-info">Angka di pin = nomor tugas. Ketuk pin untuk navigasi.</small>
            @if($ruteSemua)
                <a href="{{ $ruteSemua }}" target="_blank" rel="noopener" class="btn btn-outline-primary w-100 mt-2 btn-nav">
                    <i class="bi bi-signpost-split-fill"></i> Rute semua tugas ({{ $semua->count() }} tujuan, urut nomor)
                </a>
            @endif
        @endif
    </div>
@endif

@foreach(['photo', 'paid', 'payment_method', 'reason'] as $field)
    @error($field) <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
@endforeach

@forelse($active as $i => $t)
    @php
        $wa = R::waLink($t->customer?->phone);
        $order = $t->order;
        $tagih = $order ? $order->remaining() : 0;
        $c = $t->coords();
    @endphp
    <div class="job {{ $t->status === 'on_the_way' ? 'on-the-way' : '' }}" id="tugas-{{ $t->id }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <span class="type-pill" style="background:{{ $t->type === 'pickup' ? 'var(--purple)' : 'var(--teal)' }}">
                    <i class="bi {{ $t->type === 'pickup' ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up' }}"></i>
                    {{ $t->type === 'pickup' ? 'JEMPUT cucian' : 'ANTAR cucian' }}
                </span>
                <div class="fw-bold fs-4 mt-1"><span class="no">{{ $i + 1 }}</span>{{ $t->customer?->name ?? '-' }}</div>
            </div>
            <span class="badge rounded-pill" style="background:{{ $t->statusColor() }}">{{ $t->statusLabel() }}</span>
        </div>

        <dl>
            <dt>Tanggal</dt>
            <dd class="fw-bold">{{ $t->scheduled_date->locale('id')->translatedFormat('l, d-m-Y') }}
                @if($t->scheduled_date->isToday()) <span class="badge bg-danger">Hari ini</span> @endif</dd>
            <dt>Alamat</dt> <dd>{{ $t->address }}</dd>
            <dt>Jarak</dt>
            <dd>
                @if($c)
                    <span class="js-jarak opacity-75" data-lat="{{ $c[0] }}" data-lng="{{ $c[1] }}">ketuk "Lokasi saya" di peta</span>
                @else
                    <span class="opacity-75">tanpa titik peta (navigasi memakai alamat)</span>
                @endif
            </dd>
            <dt>No. HP</dt> <dd>{{ $t->customer?->phone ?? '-' }}</dd>
            @if($t->type === 'pickup' && $t->reservation)
                <dt>Layanan</dt> <dd>{{ $t->reservation->service }}</dd>
                @if($t->reservation->estimated_weight) <dt>Perkiraan berat</dt> <dd>{{ $t->reservation->estimated_weight }}</dd> @endif
                @if($t->reservation->estimated_price) <dt>Perkiraan harga</dt> <dd>{{ $t->reservation->estimated_price }}</dd> @endif
                @if($t->reservation->pickup_fee) <dt>Ongkos</dt> <dd>{{ $t->reservation->pickup_fee }}</dd> @endif
                @if($t->reservation->notes) <dt>Catatan</dt> <dd>{{ $t->reservation->notes }}</dd> @endif
            @endif
            @if($order) <dt>Nota</dt> <dd>{{ $order->invoice_number }}</dd> @endif
            @if($t->type === 'delivery' && $t->handoverPhoto)
                <dt>Serah kasir</dt>
                <dd>
                    <a href="{{ route('kasir.courier-tasks.photo', [$t, 'kind' => 'handover']) }}" target="_blank" rel="noopener" style="color:var(--teal)">
                        <i class="bi bi-camera-fill"></i> Lihat foto serah dari kasir
                    </a>
                </dd>
            @endif
            @if($t->notes) <dt>Pesan kasir</dt> <dd class="fw-bold">{{ $t->notes }}</dd> @endif
        </dl>

        @if($tagih > 0)
            <div class="collect mb-2"><i class="bi bi-cash-coin"></i> Tagih ke pelanggan: <b class="fs-5">{{ $rp($tagih) }}</b></div>
        @elseif($order)
            <div class="small mb-2" style="color:var(--teal)"><i class="bi bi-check-circle-fill"></i> Tagihan sudah lunas, tinggal serahkan cucian.</div>
        @endif

        <!-- Navigasi Google Maps: mode motor, langsung mulai dari posisi kurir -->
        <a href="{{ $t->routeUrl() }}" target="_blank" rel="noopener" class="btn btn-primary w-100 mb-2 btn-nav">
            <i class="bi bi-sign-turn-right-fill"></i> Navigasi ke tujuan
        </a>
        <!-- Hubungi pelanggan & lihat titik di peta -->
        <div class="actions mb-2">
            @if($wa) <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-whatsapp"></i><br>Chat</a>
            @else <span class="btn btn-light disabled"><i class="bi bi-whatsapp"></i><br>Chat</span> @endif
            <a href="tel:{{ $t->customer?->phone }}" class="btn btn-outline-secondary"><i class="bi bi-telephone-fill"></i><br>Telepon</a>
            @if($c)
                <button type="button" class="btn btn-outline-primary js-lihat-peta" data-id="{{ $t->id }}"><i class="bi bi-geo-alt-fill"></i><br>Di peta</button>
            @else
                <span class="btn btn-light disabled"><i class="bi bi-geo-alt"></i><br>Di peta</span>
            @endif
        </div>

        @if($t->status === 'assigned')
            <form method="POST" action="{{ route('kasir.courier.start', $t) }}" class="mb-2">
                @csrf
                <button class="btn w-100 text-white fw-bold py-2" style="background:#6b8fd6; border-radius:14px"><i class="bi bi-scooter"></i> Berangkat sekarang</button>
            </form>
        @endif

        <!-- Selesai: wajib foto bukti -->
        <form method="POST" action="{{ route('kasir.courier.complete', $t) }}" enctype="multipart/form-data" class="js-finish">
            @csrf
            <div class="photo-box mb-2">
                <label class="btn text-white w-100 py-2" style="background:var(--teal)">
                    <i class="bi bi-camera-fill"></i> {{ $t->type === 'pickup' ? 'Foto cucian yang dijemput' : 'Foto saat cucian diserahkan' }}
                    {{-- capture="environment": langsung membuka kamera belakang HP --}}
                    <input type="file" name="photo" accept="image/*" capture="environment" class="d-none js-photo">
                </label>
                <small class="d-block opacity-75 mt-1 js-photo-info">Belum ada foto.</small>
                <img class="js-photo-preview" alt="Pratinjau foto" hidden>
            </div>

            @if($tagih > 0)
                <div class="collect mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="paid" value="1" id="paid-{{ $t->id }}" required>
                        <label class="form-check-label fw-bold" for="paid-{{ $t->id }}">Uang {{ $rp($tagih) }} sudah diterima</label>
                    </div>
                    <select name="payment_method" class="form-select mt-2" aria-label="Cara bayar">
                        @foreach(\App\Models\Order::PAYMENT_METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <input type="text" name="courier_notes" maxlength="255" class="form-control mb-2" placeholder="Catatan (opsional), cth: dititip ke satpam">
            <button class="btn w-100 text-white fw-bold py-2 js-submit" style="background:var(--purple); border-radius:14px">
                <i class="bi bi-check2-circle"></i> {{ $t->type === 'pickup' ? 'Selesai dijemput' : 'Selesai diantar' }}
            </button>
        </form>

        <!-- Gagal: wajib alasan -->
        <details class="mt-2">
            <summary class="small" style="color:#e05a6b; cursor:pointer"><i class="bi bi-exclamation-triangle"></i> Tidak bisa {{ $t->type === 'pickup' ? 'dijemput' : 'diantar' }}?</summary>
            <form method="POST" action="{{ route('kasir.courier.fail', $t) }}" class="mt-2" onsubmit="return confirm('Tandai tugas ini gagal?')">
                @csrf
                <textarea name="reason" rows="2" class="form-control mb-2" required minlength="5" maxlength="255"
                          placeholder="Alasan, cth: rumah kosong, nomor tidak bisa dihubungi"></textarea>
                <button class="btn btn-outline-danger w-100" style="border-radius:14px">Tandai gagal</button>
            </form>
        </details>
    </div>
@empty
    <div class="k-card text-center">
        <i class="bi bi-emoji-smile fs-1" style="color:var(--teal)"></i>
        <p class="mb-0 mt-2">Belum ada tugas. Halaman ini mengecek tugas baru otomatis dan berbunyi saat kasir menugaskan Anda.</p>
    </div>
@endforelse

{{-- Tugas jemput yang sudah dijemput dari pelanggan, tinggal diserahkan ke kasir dengan foto kamera --}}
@if(isset($pendingHandover) && $pendingHandover->isNotEmpty())
    <div class="k-card mt-3">
        <h5 class="mb-1"><i class="bi bi-box-seam-fill"></i> Serahkan Cucian Jemputan ke Kasir</h5>
        <p class="small opacity-75 mb-3">Cucian di bawah ini sudah Anda jemput dari pelanggan. Ambil foto kamera saat menyerahkannya ke kasir di outlet.</p>

        @foreach($pendingHandover as $t)
            <div class="job mb-3" style="border-color:var(--purple); background:#fff9fb">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <span class="type-pill" style="background:var(--purple)"><i class="bi bi-arrow-right-circle-fill"></i> SERAH KE KASIR</span>
                        <div class="fw-bold fs-5 mt-1">{{ $t->customer?->name ?? '-' }}</div>
                        <small class="opacity-75">Layanan: {{ $t->reservation?->service ?? '-' }} · Dijemput {{ $t->finished_at?->format('d-m H:i') }}</small>
                    </div>
                    @if($t->photo)
                        <a href="{{ route('kasir.courier-tasks.photo', $t) }}" target="_blank" rel="noopener" class="small" style="color:var(--teal)">
                            <i class="bi bi-image"></i> Foto jemput
                        </a>
                    @endif
                </div>

                <form method="POST" action="{{ route('kasir.courier.handover', $t) }}" enctype="multipart/form-data" class="js-finish">
                    @csrf
                    <div class="photo-box mb-2">
                        <label class="btn text-white w-100 py-2" style="background:var(--teal)">
                            <i class="bi bi-camera-fill"></i> Foto saat menyerahkan cucian ke kasir
                            <input type="file" name="photo" accept="image/*" capture="environment" class="d-none js-photo">
                        </label>
                        <small class="d-block opacity-75 mt-1 js-photo-info">Belum ada foto.</small>
                        <img class="js-photo-preview" alt="Pratinjau foto" hidden>
                    </div>
                    <button class="btn w-100 text-white fw-bold py-2 js-submit" style="background:var(--purple); border-radius:14px">
                        <i class="bi bi-check2-circle"></i> Serahkan ke kasir
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endif

@if($history->isNotEmpty())
    <div class="k-card mt-3">
        <h5 class="mb-2">Riwayat 7 hari terakhir</h5>
        @foreach($history as $h)
            <div class="history d-flex justify-content-between align-items-center gap-2">
                <div>
                    <b>{{ $h->typeLabel() }}</b> · {{ $h->customer?->name ?? '-' }}
                    <div class="small opacity-75">{{ ($h->finished_at ?? $h->updated_at)?->format('d-m H:i') }}
                        @if($h->courier_notes) · {{ $h->courier_notes }} @endif</div>
                </div>
                <div class="text-end text-nowrap">
                    <span class="badge rounded-pill" style="background:{{ $h->statusColor() }}">{{ $h->statusLabel() }}</span>
                    @if($h->photo) <a href="{{ route('kasir.courier-tasks.photo', $h) }}" target="_blank" rel="noopener" class="ms-1" style="color:var(--teal)" title="Foto di pelanggan"><i class="bi bi-image"></i></a> @endif
                    @if($h->handoverPhoto) <a href="{{ route('kasir.courier-tasks.photo', [$h, 'kind' => 'handover']) }}" target="_blank" rel="noopener" class="ms-1" style="color:var(--purple)" title="Foto serah terima kasir"><i class="bi bi-camera-fill"></i></a> @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

<script>
    // Foto bukti: tampilkan pratinjau dan perkecil (maks. 1024 px, JPEG ± 50–150 KB) supaya hemat kuota & database
    document.querySelectorAll('.js-finish').forEach(function (form) {
        const input = form.querySelector('.js-photo');
        const info = form.querySelector('.js-photo-info');
        const preview = form.querySelector('.js-photo-preview');
        const submit = form.querySelector('.js-submit');

        input.addEventListener('change', async function () {
            const file = input.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            info.textContent = 'Memproses foto...';
            submit.disabled = true;
            try {
                const img = await createImageBitmap(file);
                const skala = Math.min(1, 1024 / Math.max(img.width, img.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * skala);
                canvas.height = Math.round(img.height * skala);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.7));
                if (blob) {
                    const dt = new DataTransfer();
                    dt.items.add(new File([blob], 'bukti.jpg', { type: 'image/jpeg' }));
                    input.files = dt.files;                                    // Ganti dengan foto yang sudah diperkecil
                }
                info.textContent = 'Foto siap (' + Math.round(input.files[0].size / 1024) + ' KB). Ketuk tombol foto lagi untuk mengulang.';
            } catch (e) {
                info.textContent = 'Foto siap. Ketuk tombol foto lagi untuk mengulang.';   // Browser lama: diperkecil di server
            }
            submit.disabled = false;
        });

        form.addEventListener('submit', function (e) {
            // Kotak foto tersembunyi (diganti tombol), jadi cek wajib foto di sini
            if (!input.files.length) {
                e.preventDefault();
                info.innerHTML = '<b style="color:#e05a6b">Ambil foto bukti dulu.</b>';
                return;
            }
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim foto...';
        });
    });
</script>

@if($titik->isNotEmpty())
<script>
    // ===== Peta tujuan (Leaflet + OpenStreetMap, tanpa API key) =====
    (function () {
        const TITIK = @json($titik);
        const info = document.getElementById('peta-info');
        const pins = {};
        let peta = null, pinSaya = null, pertama = true;

        // Muat Leaflet dari CDN hanya saat halaman ini dibuka
        function muatLeaflet() {
            return new Promise(function (ok, gagal) {
                if (window.L) return ok();
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://cdn.jsdelivr.net/npm/leaflet' + '@' + '1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
                const js = document.createElement('script');
                js.src = 'https://cdn.jsdelivr.net/npm/leaflet' + '@' + '1.9.4/dist/leaflet.js';
                js.onload = ok;
                js.onerror = gagal;
                document.head.appendChild(js);
            });
        }

        // Amankan teks sebelum dimasukkan ke popup peta
        const esc = s => String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

        muatLeaflet().then(function () {
            peta = L.map('peta-kurir', { scrollWheelZoom: false });
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(peta);

            TITIK.forEach(function (p) {
                const ikon = L.divIcon({ className: 'pin-no', html: String(p.no), iconSize: [30, 30], iconAnchor: [15, 15] });
                pins[p.id] = L.marker([p.lat, p.lng], { icon: ikon }).addTo(peta).bindPopup(
                    '<b>' + p.no + '. ' + esc(p.name) + '</b> (' + esc(p.type) + ')<br><small>' + esc(p.address) + '</small><br>'
                    + '<a href="' + esc(p.nav) + '" target="_blank" rel="noopener" class="btn btn-sm btn-primary text-white mt-2">Navigasi</a>');
            });
            const batas = L.latLngBounds(TITIK.map(p => [p.lat, p.lng]));
            if (TITIK.length > 1) peta.fitBounds(batas, { padding: [30, 30] }); else peta.setView(batas.getCenter(), 16);
        }).catch(function () {
            info.textContent = 'Peta gagal dimuat (cek koneksi). Tombol "Navigasi ke tujuan" tetap bisa dipakai.';
        });

        // Jarak garis lurus (km) dari posisi kurir ke tujuan
        function jarakKm(a, b) {
            const r = d => d * Math.PI / 180;
            const h = Math.sin(r(b[0] - a[0]) / 2) ** 2 + Math.cos(r(a[0])) * Math.cos(r(b[0])) * Math.sin(r(b[1] - a[1]) / 2) ** 2;
            return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(h)));
        }

        // "Lokasi saya": titik biru posisi kurir (ikut bergerak) + jarak ke setiap tujuan di kartu
        document.getElementById('btn-lokasi-saya').addEventListener('click', function () {
            if (!navigator.geolocation) { info.textContent = 'HP/browser ini tidak mendukung lokasi.'; return; }
            info.textContent = 'Mencari lokasi Anda... izinkan akses lokasi jika diminta.';
            navigator.geolocation.watchPosition(function (pos) {
                const saya = [pos.coords.latitude, pos.coords.longitude];
                if (peta) {
                    if (!pinSaya) {
                        pinSaya = L.marker(saya, { icon: L.divIcon({ className: 'pin-me', iconSize: [18, 18], iconAnchor: [9, 9] }) })
                            .addTo(peta).bindPopup('Posisi Anda');
                    } else {
                        pinSaya.setLatLng(saya);
                    }
                    if (pertama) {                                        // Tampilkan semua tujuan + posisi kurir (sekali saja)
                        peta.fitBounds(L.latLngBounds(TITIK.map(p => [p.lat, p.lng]).concat([saya])), { padding: [30, 30] });
                        pertama = false;
                    }
                }
                document.querySelectorAll('.js-jarak').forEach(function (el) {
                    const km = jarakKm(saya, [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)]);
                    el.classList.remove('opacity-75');
                    el.innerHTML = '<b>±' + km.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' km</b> dari Anda (garis lurus)';
                });
                info.textContent = 'Titik biru = posisi Anda (akurasi ±' + Math.round(pos.coords.accuracy) + ' m).';
            }, function () {
                info.textContent = 'Lokasi tidak bisa diambil (izin ditolak atau GPS mati).';
            }, { enableHighAccuracy: true, maximumAge: 15000 });
        });

        // Tombol "Di peta" di kartu: gulir ke peta lalu buka pin tugas itu
        document.querySelectorAll('.js-lihat-peta').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const p = TITIK.find(x => String(x.id) === btn.dataset.id);
                if (!p || !peta) return;
                document.getElementById('peta-kurir').scrollIntoView({ behavior: 'smooth', block: 'center' });
                peta.setView([p.lat, p.lng], 17);
                pins[p.id].openPopup();
            });
        });
    })();
</script>
@endif
@endsection
