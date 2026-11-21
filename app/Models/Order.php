<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;                        // Import base class Model Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsTo;           // Import relasi Many-to-One
use Illuminate\Database\Eloquent\Relations\HasMany;             // Import relasi One-to-Many

class Order extends Model
{
    /** Tahap pengerjaan cucian (urutan sama dengan enum order_status di database) beserta nama tampilannya. */
    public const STATUSES = [
        'received'         => 'Diterima',
        'washing'          => 'Dicuci',
        'drying'           => 'Dikeringkan',
        'ironing'          => 'Disetrika',
        'ready_for_pickup' => 'Siap diambil',
        'completed'        => 'Sudah diambil',
    ];

    /** Status pembayaran (enum payment_status) beserta nama tampilannya. */
    public const PAYMENT_STATUSES = [
        'unpaid'  => 'Belum bayar',
        'partial' => 'DP',
        'paid'    => 'Lunas',
    ];

    /** Metode pembayaran (enum payments.payment_method). */
    public const PAYMENT_METHODS = [
        'cash'     => 'Tunai',
        'qris'     => 'QRIS',
        'transfer' => 'Transfer',
    ];

    protected $fillable = [
        'invoice_number',                                       // Nomor unik nota transaksi
        'customer_id',                                          // Relasi ke pelanggan yang memesan
        'user_id',                                              // Relasi ke staf/kasir pembuat order
        'order_date',                                           // Waktu nota dibuat
        'estimate_date',                                        // Waktu target estimasi cucian selesai
        'completion_date',                                      // Waktu aktual cucian selesai
        'total_amount',                                         // Total tagihan kotor sebelum diskon
        'discount',                                             // Potongan harga promosi
        'final_amount',                                         // Total tagihan bersih yang wajib dibayar
        'paid_amount',                                          // Akumulasi dana yang sudah disetor
        'payment_status',                                       // Status bayar: unpaid, partial, paid
        'order_status',                                         // Progres: received, washing, drying, ironing, ready_for_pickup, completed
        'notes'                                                 // Catatan khusus pelanggan
    ];

    protected $casts = [
        'order_date'      => 'datetime',                        // Konversi otomatis format tanggal-jam
        'estimate_date'   => 'datetime',                        // Konversi otomatis format tanggal-jam
        'completion_date' => 'datetime',                        // Konversi otomatis format tanggal-jam
        'total_amount'    => 'decimal:2',                       // Format angka desimal uang
        'discount'        => 'decimal:2',                       // Format angka desimal uang
        'final_amount'    => 'decimal:2',                       // Format angka desimal uang
        'paid_amount'     => 'decimal:2',                       // Format angka desimal uang
    ];

    /** Sisa tagihan yang belum dibayar. */
    public function remaining(): float
    {
        return max(0, round((float) $this->final_amount - (float) $this->paid_amount, 2));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->order_status] ?? $this->order_status;
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    /** Tahap berikutnya di antrean (null jika sudah "Siap diambil"/"Sudah diambil"; pengambilan dicatat kasir). */
    public function nextStatus(): ?string
    {
        $flow = ['received', 'washing', 'drying', 'ironing', 'ready_for_pickup'];
        $i = array_search($this->order_status, $flow, true);
        return ($i !== false && $i < count($flow) - 1) ? $flow[$i + 1] : null;
    }

    /** Tahap sebelumnya di antrean (null jika masih "Diterima" atau sudah diambil) — untuk membatalkan salah pencet. */
    public function previousStatus(): ?string
    {
        $flow = ['received', 'washing', 'drying', 'ironing', 'ready_for_pickup'];
        $i = array_search($this->order_status, $flow, true);
        return ($i !== false && $i > 0) ? $flow[$i - 1] : null;
    }

    /** Status bayar dihitung dari jumlah yang sudah dibayar dibanding total tagihan. */
    public static function paymentStatusFor(float $paid, float $final): string
    {
        if ($paid <= 0) return 'unpaid';
        return $paid + 0.001 >= $final ? 'paid' : 'partial';
    }

    /**
     * Nomor nota berikutnya untuk hari ini, cth: INV-20261106-001, INV-20261106-002, ...
     * Dipanggil di dalam DB::transaction; lockForUpdate mencegah 2 kasir mendapat nomor yang sama.
     */
    public static function nextInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $last = self::where('invoice_number', 'like', $prefix . '%')
            ->lockForUpdate()->orderByDesc('invoice_number')->value('invoice_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);               // Relasi: Pesanan dimiliki oleh 1 customer
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);                   // Relasi: Pesanan dilayani oleh 1 kasir
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);                // Relasi: Pesanan memiliki banyak baris rincian item
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);                  // Relasi: Pesanan bisa memiliki banyak setoran pembayaran (DP/lunas)
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);           // Relasi: Pesanan memiliki jejak log riwayat pengerjaan
    }

    public function courierTasks(): HasMany
    {
        return $this->hasMany(CourierTask::class)->latest('id'); // Tugas antar kurir untuk nota ini (terbaru di atas)
    }
}