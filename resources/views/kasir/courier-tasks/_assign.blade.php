{{--
    Form "Tugaskan kurir" (dipakai di kartu Reservasi = jemput, dan halaman nota = antar).
    Parameter: action (URL), couriers (daftar kurir), delivery (true = antar: ada tanggal & alamat), address (alamat bawaan)
--}}
@php $delivery = $delivery ?? false; @endphp
@if($couriers->isEmpty())
    <small class="d-block opacity-75"><i class="bi bi-info-circle"></i> Belum ada akun kurir. Owner bisa membuatnya di menu Kelola Staf (role Kurir).</small>
@else
    <form method="POST" action="{{ $action }}" @if($delivery) enctype="multipart/form-data" @endif class="row g-2 m-0">
        @csrf
        <div class="{{ $delivery ? 'col-sm-6' : 'col-sm-5' }}">
            <select name="courier_id" class="form-select form-control" required aria-label="Pilih kurir">
                <option value="">-- Pilih kurir --</option>
                @foreach($couriers as $c)
                    <option value="{{ $c->id }}" @selected(old('courier_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @if($delivery)
            <div class="col-sm-6">
                <input type="date" name="scheduled_date" class="form-control" required min="{{ now()->toDateString() }}"
                       value="{{ old('scheduled_date', now()->toDateString()) }}" aria-label="Tanggal antar">
            </div>
            <div class="col-12">
                <textarea name="address" rows="2" class="form-control" maxlength="500" placeholder="Alamat antar"
                          aria-label="Alamat antar">{{ old('address', $address ?? '') }}</textarea>
            </div>
        @endif
        <div class="{{ $delivery ? 'col-12' : 'col-sm-7' }}">
            <input type="text" name="notes" maxlength="255" class="form-control" value="{{ old('notes') }}"
                   placeholder="Pesan untuk kurir (opsional)" aria-label="Pesan untuk kurir">
        </div>
        @if($delivery)
            <div class="col-12">
                <div class="p-2 rounded-3 text-center" style="border:2px dashed var(--mint-line); background:#fff">
                    <label class="btn btn-sm text-white rounded-pill px-3" style="background:var(--teal); cursor:pointer">
                        <i class="bi bi-camera-fill"></i> Foto serahkan cucian ke kurir (opsional)
                        <input type="file" name="handover_photo" accept="image/*" capture="environment" class="d-none js-handover-input">
                    </label>
                    <small class="d-block opacity-75 mt-1 js-handover-info">Bisa difoto sekarang atau saat kurir mengambil cucian.</small>
                    <img class="js-handover-preview mt-1" alt="Pratinjau foto" style="max-width:100%;max-height:140px;border-radius:10px" hidden>
                </div>
            </div>
        @endif
        <div class="col-12">
            <button class="btn btn-sm text-white rounded-pill" style="background:var(--purple)">
                <i class="bi bi-truck"></i> {{ $delivery ? 'Tugaskan antar' : 'Tugaskan jemput' }}
            </button>
        </div>
    </form>
@endif
