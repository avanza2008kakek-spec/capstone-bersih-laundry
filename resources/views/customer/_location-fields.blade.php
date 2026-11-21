{{--
    Komponen isian Alamat + Lokasi Google Maps (dipakai di form Reservasi dan halaman Edit Alamat).
    Cara pakai: include('customer._location-fields', ['address' => ..., 'mapsUrl' => ..., 'mapsRequired' => true/false])
    - address      : isi awal alamat
    - mapsUrl      : isi awal link Google Maps
    - mapsRequired : true = lokasi wajib diisi (halaman Edit Alamat), false = disarankan saja
    - addressLabel : (opsional) judul kotak alamat, bawaan "Alamat penjemputan"
    - saveNote     : (opsional) keterangan penyimpanan di bawah link lokasi
    - showPickupFee: (opsional) true = tampilkan perkiraan ongkos jemput dari titik lokasi
--}}
@php
    $addressLabel = $addressLabel ?? 'Alamat penjemputan';
    $saveNote = $saveNote ?? 'Jika alamat & lokasi terisi, keduanya disimpan sebagai alamat default Anda.';
    $showPickupFee = $showPickupFee ?? false;
    // Data untuk hitung ongkos jemput di browser: titik laundry + tarif per km (diatur owner)
    $feeSettings = app(\App\Support\Settings::class);
    $shopCoords = \App\Support\PickupFee::coords($feeSettings->get('shop_maps_url'));
    $feeRates = $feeSettings->pickupRates();
@endphp
<style>
    /* Pratinjau peta lokasi penjemputan */
    .map-preview { border: 2px solid var(--mint-line, #a8e0d2); border-radius: 16px; overflow: hidden; height: 200px; }
    .map-preview iframe { width: 100%; height: 100%; border: 0; }
    /* Keterangan kuning "cek kembali alamat" & kotak alamat yang baru diisi otomatis */
    .address-check { background: #fff6d6; border: 1.5px solid #f2d27a; border-radius: 12px; padding: 8px 12px; font-size: .88rem; }
    .address-auto { border-color: #f2c94c !important; box-shadow: 0 0 0 4px rgba(242,201,76,.25) !important; }
    /* Panel "Pilih di peta": kotak cari + peta interaktif + tombol pakai titik */
    .map-picker { border: 2px solid var(--mint-line, #a8e0d2); border-radius: 16px; padding: 12px; background: #fff; }
    .map-picker #picker-map { height: 320px; border-radius: 12px; overflow: hidden; z-index: 0; }
    .map-picker .search-results { max-height: 180px; overflow-y: auto; }
    .map-picker .search-results button { display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px dashed #d8efe8;
                                         background: #fff; padding: 8px 6px; font-size: .88rem; }
    .map-picker .search-results button:hover { background: var(--mint, #e8f8f3); }
    @media (max-width: 575.98px) { .map-picker #picker-map { height: 260px; } }
</style>

<!-- Alamat: wajib diisi -->
<div class="col-12 mb-3">
    <label class="form-label fw-bold" for="r-alamat">{{ $addressLabel }} <span class="text-danger">*</span></label>
    <textarea id="r-alamat" name="address" rows="3" required minlength="10"
              placeholder="Nama jalan, nomor rumah, RT/RW, patokan"
              class="form-control @error('address') is-invalid @enderror">{{ old('address', $address) }}</textarea>
    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <!-- Keterangan cek kembali: muncul setelah alamat diisi otomatis dari tombol "Gunakan lokasi saya" -->
    <div class="address-check mt-2" id="alamat-cek" hidden></div>
</div>

<!-- Lokasi Google Maps: pakai GPS HP atau tempel link dari Google Maps -->
<div class="col-12 mb-3">
    <label class="form-label fw-bold" for="r-maps">
        Lokasi di Google Maps
        @if($mapsRequired) <span class="text-danger">*</span> @else <small class="fw-normal opacity-75">(disarankan)</small> @endif
    </label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn-pill btn-yellow py-1 px-3" id="btn-pilih-peta"><i class="bi bi-map-fill"></i> Pilih di peta</button>
        <button type="button" class="btn-pill btn-white py-1 px-3" id="btn-gps"><i class="bi bi-crosshair"></i> Gunakan lokasi saya</button>
        <button type="button" class="btn-pill btn-white py-1 px-3" id="btn-cari-maps"><i class="bi bi-geo-alt-fill"></i> Cari di Google Maps</button>
    </div>

    <!-- Panel "Pilih di peta": cari jalan/tempat, lalu ketuk peta atau geser pin ke rumah -->
    <div class="map-picker mb-2" id="map-picker" hidden>
        <div class="input-group mb-2">
            <input type="search" id="picker-q" class="form-control" placeholder="Cari nama jalan, perumahan, atau tempat terdekat">
            <button type="button" class="btn-pill btn-white py-1 px-3 ms-1" id="picker-cari"><i class="bi bi-search"></i> Cari</button>
        </div>
        <div class="search-results mb-2" id="picker-hasil"></div>
        <div id="picker-map"></div>
        <small class="d-block opacity-75 mt-2" id="picker-info">Ketuk peta atau geser pin tepat ke rumah Anda.</small>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn-pill btn-yellow py-1 px-3" id="picker-pakai"><i class="bi bi-check-lg"></i> Pakai titik ini</button>
            <button type="button" class="btn-pill btn-white py-1 px-3" id="picker-tutup">Tutup</button>
        </div>
    </div>
    <input type="url" id="r-maps" name="maps_url" maxlength="500" @required($mapsRequired)
           value="{{ old('maps_url', $mapsUrl) }}"
           placeholder="Tempel link lokasi, cth: https://maps.app.goo.gl/..."
           class="form-control @error('maps_url') is-invalid @enderror">
    @error('maps_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <small class="opacity-75 d-block mt-1" id="maps-hint">
        Di Google Maps: tekan lama titik rumah → <b>Bagikan</b> → <b>Salin link</b>, lalu tempel di sini.
    </small>
    <small class="d-block mt-1" style="color:var(--teal, #47a990)">
        <i class="bi bi-bookmark-check-fill"></i> {{ $saveNote }}
    </small>
    @if($showPickupFee && $shopCoords && $feeRates)
        <!-- Perkiraan ongkos jemput (dihitung ulang setiap titik lokasi berubah) -->
        <div class="mt-2 p-2 rounded-3 small" style="background:var(--mint)" id="ongkos-info" hidden></div>
    @endif
    <!-- Pratinjau peta (muncul jika link berisi koordinat) -->
    <div class="map-preview mt-2" id="map-preview" hidden>
        <iframe id="map-frame" title="Pratinjau lokasi" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>

<script>
    // ===== Lokasi Google Maps: tombol GPS, tombol Cari di Google Maps, dan pratinjau peta =====
    (function () {
        const mapsInput = document.getElementById('r-maps');
        const preview = document.getElementById('map-preview');
        const frame = document.getElementById('map-frame');
        const hint = document.getElementById('maps-hint');

        // Ambil koordinat dari link Google Maps (bentuk yang sama dengan PickupFee::coords di server):
        // ?q=lat,lng · /search/lat,+lng · !3dlat!4dlng (titik tempat) · @lat,lng (tengah peta)
        function ambilKoordinat(url) {
            let u = url;
            try { u = decodeURIComponent(url.replace(/\+/g, ' ')); } catch (e) {}
            const n = '(-?\\d+(?:\\.\\d+)?)';
            const m = u.match(new RegExp('[?&](?:q|query|ll)=' + n + ',\\s*' + n))
                   || u.match(new RegExp('/maps/(?:search|place)/' + n + ',\\s*' + n))
                   || u.match(new RegExp('!3d' + n + '!4d' + n))
                   || u.match(new RegExp('@' + n + ',' + n));
            return m ? m[1] + ',' + m[2] : null;
        }

        // Perkiraan ongkos jemput: jarak garis lurus dari laundry (rumus haversine) lalu cocokkan dengan tarif per km.
        // Hasilnya tampil di bawah kotak lokasi (#ongkos-info) dan di ringkasan dekat tombol kirim (#ringkasan-ongkos-nilai, jika ada).
        const ongkosBox = document.getElementById('ongkos-info');
        const TOKO = @json($shopCoords);
        const TARIF = @json($feeRates);
        function perbaruiOngkos(koordinat) {
            if (!ongkosBox) return;                              // Owner belum mengatur lokasi laundry / tarif → dihitung admin
            // Dicari setiap kali, karena kotak ringkasan ada di bawah komponen ini (belum ada saat skrip pertama jalan)
            const ringkasan = document.getElementById('ringkasan-ongkos-nilai');
            if (!koordinat) {
                const pesan = 'pilih titik lokasi dulu';
                ongkosBox.innerHTML = '<i class="bi bi-truck"></i> Ongkos jemput dihitung otomatis setelah titik lokasi dipilih (tombol <b>Pilih di peta</b> atau <b>Gunakan lokasi saya</b>).';
                ongkosBox.hidden = false;
                if (ringkasan) ringkasan.innerHTML = '<span class="fw-normal opacity-75">' + pesan + '</span>';
                return;
            }
            const [lat2, lng2] = koordinat.split(',').map(Number);
            const r = d => d * Math.PI / 180;
            const h = Math.sin(r(lat2 - TOKO[0]) / 2) ** 2 + Math.cos(r(TOKO[0])) * Math.cos(r(lat2)) * Math.sin(r(lng2 - TOKO[1]) / 2) ** 2;
            const km = Math.round(6371 * 2 * Math.asin(Math.min(1, Math.sqrt(h))) * 10) / 10;
            const tarif = TARIF.find(t => km <= t.max_km);
            const jarak = '±' + km.toLocaleString('id-ID') + ' km';
            const harga = tarif ? (tarif.price > 0 ? 'Rp' + tarif.price.toLocaleString('id-ID') : 'Gratis') : 'di luar area jemput';
            ongkosBox.innerHTML = '<i class="bi bi-truck"></i> Perkiraan ongkos jemput: <b>' + harga + '</b> (' + jarak
                + (tarif ? ')' : ', admin akan mengonfirmasi)');
            ongkosBox.hidden = false;
            if (ringkasan) ringkasan.textContent = harga + ' (' + jarak + ')';
        }

        // Tampilkan peta kecil (tanpa API key) jika link berisi koordinat
        function perbaruiPratinjau() {
            const koordinat = ambilKoordinat(mapsInput.value);
            perbaruiOngkos(koordinat);
            if (koordinat) {
                frame.src = 'https://maps.google.com/maps?q=' + koordinat + '&z=17&output=embed';
                preview.hidden = false;
            } else {
                preview.hidden = true;
            }
        }

        // Link pendek dari tombol "Bagikan" (maps.app.goo.gl / goo.gl/maps) tidak berisi koordinat:
        // minta server membukanya, lalu ganti isian dengan link berkoordinat supaya ongkos & pratinjau langsung muncul
        const LINK_PENDEK = /^https:\/\/(maps\.app\.goo\.gl|goo\.gl\/maps)\//i;
        let timerLink = null;
        async function bukaLinkPendek() {
            const url = mapsInput.value.trim();
            if (!LINK_PENDEK.test(url) || ambilKoordinat(url)) return;
            hint.textContent = 'Membaca titik lokasi dari link...';
            try {
                const res = await fetch(@json(route('address.resolve-link')) + '?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (mapsInput.value.trim() !== url) return;      // Isian sudah diganti pelanggan selama menunggu
                if (data.ok) {
                    mapsInput.value = data.url;
                    hint.textContent = 'Titik lokasi terbaca dari link. Cek pratinjau peta di bawah, pastikan sudah benar.';
                    perbaruiPratinjau();
                } else {
                    hint.textContent = data.error || 'Titik lokasi dari link ini tidak terbaca. Gunakan tombol "Pilih di peta".';
                }
            } catch (e) {
                hint.textContent = 'Titik lokasi belum terbaca (koneksi). Ongkos jemput akan dihitung admin, atau gunakan tombol "Pilih di peta".';
            }
        }

        mapsInput.addEventListener('input', function () {
            perbaruiPratinjau();
            clearTimeout(timerLink);
            timerLink = setTimeout(bukaLinkPendek, 600);         // Tunggu sebentar setelah selesai menempel/mengetik
        });
        perbaruiPratinjau();                                     // Lokasi yang pernah disimpan langsung tampil
        document.addEventListener('DOMContentLoaded', perbaruiPratinjau); // Isi juga ringkasan ongkos setelah halaman lengkap
        bukaLinkPendek();                                        // Link pendek lama yang tersimpan juga dibaca

        // Ubah titik GPS menjadi alamat (lewat server Laundry), lalu isi kotak alamat + tampilkan keterangan cek kembali
        const alamatInput = document.getElementById('r-alamat');
        const alamatCek = document.getElementById('alamat-cek');
        async function isiAlamatDariLokasi(lat, lng) {
            alamatCek.hidden = false;
            alamatCek.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mencari alamat dari lokasi Anda...';
            try {
                const res = await fetch(@json(route('address.from-location')) + '?lat=' + lat + '&lng=' + lng, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) {
                    alamatCek.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + (data.error || 'Alamat belum bisa diambil. Silakan ketik manual.');
                    return;
                }
                // Alamat yang sudah diketik pelanggan tidak ditimpa tanpa persetujuan
                const lama = alamatInput.value.trim();
                if (lama !== '' && lama !== data.address && !confirm('Ganti alamat yang sudah diisi dengan alamat dari titik lokasi?\n\n' + data.address)) {
                    alamatCek.hidden = true;
                    return;
                }
                alamatInput.value = data.address;
                alamatInput.classList.add('address-auto');      // Sorot kuning sampai pelanggan mengubahnya
                const kurang = data.missing.length
                    ? ' Lengkapi bagian bertanda <b>(isi)</b>: <b>' + data.missing.join(', ') + '</b>.'
                    : '';
                alamatCek.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> <b>Alamat diisi otomatis dari titik lokasi. Cek kembali</b> nama jalan, '
                    + 'nomor rumah, RT/RW, dan tambahkan patokan agar kurir mudah menemukan rumah Anda.' + kurang;
                alamatInput.focus();
                // Arahkan kursor ke penanda "(isi)" pertama supaya langsung bisa diketik
                const posisi = alamatInput.value.indexOf('(isi)');
                if (posisi >= 0) alamatInput.setSelectionRange(posisi, posisi + 5);
            } catch (err) {
                alamatCek.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Alamat belum bisa diambil (koneksi bermasalah). Silakan ketik manual.';
            }
        }
        // Setelah pelanggan mengubah alamat, sorotan kuning dihilangkan (keterangan tetap tampil sebagai pengingat)
        alamatInput.addEventListener('input', () => alamatInput.classList.remove('address-auto'));

        // Tombol "Gunakan lokasi saya": minta izin lokasi HP/laptop, lalu buat link Google Maps dari koordinatnya
        document.getElementById('btn-gps').addEventListener('click', function () {
            if (!navigator.geolocation) {
                hint.textContent = 'Browser ini tidak mendukung lokasi. Gunakan tombol "Cari di Google Maps".';
                return;
            }
            hint.textContent = 'Mencari lokasi Anda... izinkan akses lokasi jika diminta.';
            navigator.geolocation.getCurrentPosition(function (pos) {
                const lat = pos.coords.latitude.toFixed(6), lng = pos.coords.longitude.toFixed(6);
                mapsInput.value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
                hint.textContent = 'Lokasi ditemukan (akurasi ±' + Math.round(pos.coords.accuracy) + ' m). Cek titik di peta, pastikan sudah benar.';
                perbaruiPratinjau();
                isiAlamatDariLokasi(lat, lng);                   // Isi juga kotak alamat dari titik GPS
            }, function () {
                hint.textContent = 'Lokasi tidak bisa diambil (izin ditolak atau GPS mati). Gunakan tombol "Cari di Google Maps".';
            }, { enableHighAccuracy: true, timeout: 15000 });
        });

        // Tombol "Cari di Google Maps": buka Google Maps dengan alamat yang sudah diketik
        document.getElementById('btn-cari-maps').addEventListener('click', function () {
            const alamat = document.getElementById('r-alamat').value.trim();
            window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(alamat || @json($businessName)), '_blank');
            hint.innerHTML = 'Di Google Maps: tekan lama titik rumah → <b>Bagikan</b> → <b>Salin link</b>, lalu tempel di kotak ini.';
            mapsInput.focus();
        });

        // ===== Tombol "Pilih di peta": peta interaktif OpenStreetMap (library Leaflet, gratis tanpa API key) =====
        const picker = document.getElementById('map-picker');
        const pickerInfo = document.getElementById('picker-info');
        const hasilBox = document.getElementById('picker-hasil');
        const cariInput = document.getElementById('picker-q');
        const AWAL = [-6.2383, 106.9756];                        // Titik awal jika belum ada lokasi (Bekasi)
        let peta = null, pin = null;

        // Muat library peta hanya saat dibutuhkan (supaya halaman tetap ringan)
        function muatLeaflet() {
            if (window.L) return Promise.resolve();
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://cdn.jsdelivr.net/npm/leaflet' + '@' + '1.9.4/dist/leaflet.css';
            document.head.appendChild(css);
            return new Promise((ok, gagal) => {
                const js = document.createElement('script');
                js.src = 'https://cdn.jsdelivr.net/npm/leaflet' + '@' + '1.9.4/dist/leaflet.js';
                js.onload = ok;
                js.onerror = gagal;
                document.head.appendChild(js);
            });
        }

        // Pindahkan pin ke titik baru (dari ketukan peta, hasil cari, atau GPS)
        function taruhPin(lat, lng, zoom) {
            pin.setLatLng([lat, lng]);
            peta.setView([lat, lng], zoom || peta.getZoom());
            pickerInfo.textContent = 'Titik dipilih: ' + Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6) + '. Geser pin jika belum tepat.';
        }

        document.getElementById('btn-pilih-peta').addEventListener('click', async function () {
            picker.hidden = false;
            if (!peta) {
                pickerInfo.textContent = 'Memuat peta...';
                try {
                    await muatLeaflet();
                } catch (err) {
                    pickerInfo.textContent = 'Peta gagal dimuat (koneksi bermasalah). Gunakan tombol "Cari di Google Maps".';
                    return;
                }
                // Mulai dari lokasi yang sudah tersimpan, kalau ada
                const lama = ambilKoordinat(mapsInput.value);
                const awal = lama ? lama.split(',').map(Number) : AWAL;
                peta = L.map('picker-map').setView(awal, lama ? 18 : 14);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',          // Wajib dicantumkan sesuai aturan OpenStreetMap
                }).addTo(peta);
                pin = L.marker(awal, { draggable: true }).addTo(peta);
                pin.on('dragend', () => taruhPin(pin.getLatLng().lat, pin.getLatLng().lng));
                peta.on('click', (e) => taruhPin(e.latlng.lat, e.latlng.lng));
                pickerInfo.textContent = 'Ketuk peta atau geser pin tepat ke rumah Anda.';
            }
            setTimeout(() => peta.invalidateSize(), 50);         // Ukuran peta dihitung ulang setelah panel tampil
            picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        // Kotak cari di dalam panel peta (lewat server Laundry)
        async function cariTempat() {
            const q = cariInput.value.trim();
            if (q.length < 3) {
                hasilBox.innerHTML = '<small class="opacity-75">Ketik minimal 3 huruf.</small>';
                return;
            }
            hasilBox.innerHTML = '<small><span class="spinner-border spinner-border-sm me-1"></span> Mencari...</small>';
            try {
                const res = await fetch(@json(route('address.search-place')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) {
                    hasilBox.innerHTML = '<small>' + (data.error || 'Pencarian gagal.') + '</small>';
                    return;
                }
                if (!data.results.length) {
                    hasilBox.innerHTML = '<small class="opacity-75">Tidak ditemukan. Coba nama jalan atau tempat lain, atau geser peta manual.</small>';
                    return;
                }
                hasilBox.innerHTML = '';
                data.results.forEach((r) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = r.label;                     // textContent: nama tempat ditampilkan sebagai teks biasa (aman)
                    b.addEventListener('click', () => { taruhPin(r.lat, r.lng, 18); hasilBox.innerHTML = ''; });
                    hasilBox.appendChild(b);
                });
            } catch (err) {
                hasilBox.innerHTML = '<small>Pencarian gagal (koneksi bermasalah).</small>';
            }
        }
        document.getElementById('picker-cari').addEventListener('click', cariTempat);
        // Tekan Enter di kotak cari = cari (bukan mengirim form)
        cariInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); cariTempat(); } });

        // "Pakai titik ini": isi link Google Maps + alamat dari titik yang dipilih
        document.getElementById('picker-pakai').addEventListener('click', function () {
            const p = pin.getLatLng();
            const lat = p.lat.toFixed(6), lng = p.lng.toFixed(6);
            mapsInput.value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
            hint.textContent = 'Lokasi dipilih dari peta. Cek titik di pratinjau Google Maps di bawah.';
            perbaruiPratinjau();
            isiAlamatDariLokasi(lat, lng);
            picker.hidden = true;
        });
        document.getElementById('picker-tutup').addEventListener('click', () => { picker.hidden = true; });
    })();
</script>
