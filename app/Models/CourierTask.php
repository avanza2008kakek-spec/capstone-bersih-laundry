<?php

namespace App\Models;

use App\Support\PickupFee;                                      // Baca koordinat dari link Google Maps
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tugas kurir: jemput cucian (dari reservasi) atau antar cucian (dari nota siap diambil).
 * Alur: Ditugaskan → Dalam perjalanan → Selesai (wajib foto) / Gagal (wajib alasan). Kasir bisa membatalkan.
 */
class CourierTask extends Model
{
    public const TYPES = [
        'pickup'   => 'Jemput',
        'delivery' => 'Antar',
    ];

    public const STATUSES = [
        'assigned'   => 'Ditugaskan',
        'on_the_way' => 'Dalam perjalanan',
        'done'       => 'Selesai',
        'failed'     => 'Gagal',
        'cancelled'  => 'Dibatalkan',
    ];

    public const ACTIVE = ['assigned', 'on_the_way'];          // Tugas yang masih berjalan

    protected $fillable = ['type', 'reservation_id', 'order_id', 'customer_id', 'courier_id', 'assigned_by', 'scheduled_date',
                           'address', 'maps_url', 'notes', 'status', 'courier_notes', 'started_at', 'finished_at', 'handover_at'];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
        'handover_at'    => 'datetime',
    ];

    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function courier(): BelongsTo     { return $this->belongsTo(User::class, 'courier_id'); }
    public function assigner(): BelongsTo    { return $this->belongsTo(User::class, 'assigned_by'); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function order(): BelongsTo       { return $this->belongsTo(Order::class); }

    /** Foto bukti di lokasi pelanggan (hanya kolom kecilnya; isi foto dibaca terpisah saat foto dibuka supaya halaman tetap ringan). */
    public function photo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CourierPhoto::class)
            ->where(fn ($q) => $q->where('kind', CourierPhoto::KIND_CUSTOMER)->orWhereNull('kind'))
            ->select(CourierPhoto::META);
    }

    /** Foto bukti serah terima di outlet (jemput: kurir menyerahkan ke kasir; antar: kasir menyerahkan ke kurir). */
    public function handoverPhoto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CourierPhoto::class)
            ->where('kind', CourierPhoto::KIND_HANDOVER)
            ->select(CourierPhoto::META);
    }

    /** Titik koordinat tujuan [lat, lng] dari link Google Maps, atau null. */
    public function coords(): ?array
    {
        return PickupFee::coords($this->maps_url);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function typeLabel(): string   { return self::TYPES[$this->type] ?? $this->type; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    /** Warna lencana status. */
    public function statusColor(): string
    {
        return ['assigned' => '#e0a14a', 'on_the_way' => '#6b8fd6', 'done' => '#47a990', 'failed' => '#e05a6b', 'cancelled' => '#9aa5b1'][$this->status] ?? '#999';
    }

    /**
     * Link navigasi Google Maps ke tujuan, langsung mode navigasi motor (dari posisi kurir saat ini).
     * Tanpa titik koordinat → navigasi memakai teks alamat.
     */
    public function routeUrl(): string
    {
        $dest = ($c = $this->coords()) ? $c[0] . ',' . $c[1] : (string) $this->address;
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest) . '&travelmode=two-wheeler&dir_action=navigate';
    }

    /**
     * Pesan WhatsApp pemberitahuan tugas untuk kurir (dikirim kasir lewat tombol "Kirim WA ke kurir").
     */
    public function waMessage(string $businessName): string
    {
        $c = $this->customer;
        $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
        $lines = [
            "Halo {$this->courier?->name}, ada tugas baru dari {$businessName}:",
            '',
            '*' . ($this->type === 'pickup' ? 'JEMPUT cucian' : 'ANTAR cucian') . '*',
            'Tanggal: ' . $this->scheduled_date->locale('id')->translatedFormat('l, d-m-Y'),
            'Pelanggan: ' . ($c?->name ?? '-'),
            'No. HP: ' . ($c?->phone ?? '-'),
            'Alamat: ' . $this->address,
        ];
        if ($this->type === 'pickup' && $this->reservation) {
            $lines[] = 'Layanan: ' . $this->reservation->service;
            if ($this->reservation->estimated_weight) $lines[] = 'Perkiraan berat: ' . $this->reservation->estimated_weight;
            if ($this->reservation->estimated_price) $lines[] = 'Perkiraan harga: ' . $this->reservation->estimated_price;
            if ($this->reservation->notes) $lines[] = 'Catatan pelanggan: ' . $this->reservation->notes;
        }
        if ($this->order) {
            $lines[] = 'Nota: ' . $this->order->invoice_number;
            $sisa = $this->order->remaining();
            $lines[] = $sisa > 0 ? 'Tagih ke pelanggan: ' . $rp($sisa) : 'Tagihan: sudah lunas';
        }
        if ($this->notes) $lines[] = 'Pesan kasir: ' . $this->notes;
        $lines[] = '';
        $lines[] = 'Navigasi: ' . $this->routeUrl();
        $lines[] = 'Buka tugas & kirim foto bukti: ' . route('kasir.courier.index');

        return implode("\n", $lines);
    }

    /** Link wa.me ke nomor kurir berisi pesan tugas; null jika No. HP kurir belum diisi / tidak valid. */
    public function waLink(string $businessName): ?string
    {
        $phone = \App\Support\Phone::normalize($this->courier?->phone);
        return $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($this->waMessage($businessName)) : null;
    }

    /**
     * Jumlah tugas aktif milik kurir (lencana menu). 0 jika tabel belum dibuat di server.
     */
    public static function activeCountFor(int $courierId): int
    {
        try {
            return static::active()->where('courier_id', $courierId)->count();
        } catch (\Illuminate\Database\QueryException $e) {
            return 0;
        }
    }
}
