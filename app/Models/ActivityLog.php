<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan log aktivitas seluruh user (Owner, Kasir, Operator, Kurir, dan Pelanggan).
 * Ditampilkan di menu khusus Owner: Log Aktivitas (/kasir/log-aktivitas).
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'customer_id',
        'actor_name',
        'actor_role',
        'category',
        'action',
        'description',
        'subject_url',
        'ip_address',
        'device',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** Daftar role pelaku beserta label tampilannya. */
    public const ROLE_LABELS = [
        'owner'     => 'Owner',
        'kasir'     => 'Kasir',
        'operator'  => 'Operator',
        'kurir'     => 'Kurir',
        'pelanggan' => 'Pelanggan',
    ];

    /** Daftar kategori aktivitas beserta label & ikon Bootstrap. */
    public const CATEGORIES = [
        'auth'        => ['label' => 'Masuk / Akun',           'icon' => 'bi-shield-lock-fill',    'color' => '#6c757d'],
        'order'       => ['label' => 'Transaksi & Pembayaran', 'icon' => 'bi-receipt',             'color' => '#47a990'],
        'queue'       => ['label' => 'Antrean Cucian',         'icon' => 'bi-basket3-fill',        'color' => '#2a78b0'],
        'courier'     => ['label' => 'Tugas Kurir & Foto',     'icon' => 'bi-truck',               'color' => '#d9822b'],
        'reservation' => ['label' => 'Reservasi',              'icon' => 'bi-bell-fill',           'color' => '#e68aa6'],
        'customer'    => ['label' => 'Data Pelanggan',         'icon' => 'bi-person-lines-fill',   'color' => '#3b82f6'],
        'expense'     => ['label' => 'Pengeluaran',            'icon' => 'bi-wallet2',             'color' => '#e05a6b'],
        'service'     => ['label' => 'Layanan & Harga',        'icon' => 'bi-tags-fill',           'color' => '#8b5cf6'],
        'staff'       => ['label' => 'Kelola Staf',            'icon' => 'bi-people-fill',         'color' => '#0d9488'],
        'settings'    => ['label' => 'Pengaturan Usaha',       'icon' => 'bi-gear-fill',           'color' => '#4b3f48'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->actor_role] ?? ucfirst((string) $this->actor_role);
    }

    public function roleBadgeStyle(): string
    {
        return match ($this->actor_role) {
            'owner'     => 'background:#e68aa6;color:#fff',
            'kasir'     => 'background:#47a990;color:#fff',
            'operator'  => 'background:#2a78b0;color:#fff',
            'kurir'     => 'background:#e0a14a;color:#fff',
            'pelanggan' => 'background:#e8f8f3;color:#4b3f48;border:1px solid #a8e0d2',
            default     => 'background:#6c757d;color:#fff',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? ucfirst((string) $this->category);
    }

    public function categoryIcon(): string
    {
        return self::CATEGORIES[$this->category]['icon'] ?? 'bi-activity';
    }

    public function categoryColor(): string
    {
        return self::CATEGORIES[$this->category]['color'] ?? '#47a990';
    }

    /** Ikon Bootstrap sesuai jenis perangkat (HP / Tablet / Laptop-PC). */
    public function deviceIcon(): string
    {
        $d = mb_strtolower((string) $this->device);
        if (str_contains($d, 'ipad') || str_contains($d, 'tablet')) {
            return 'bi-tablet-landscape-fill';
        }
        if (str_contains($d, 'hp ') || str_contains($d, 'android') || str_contains($d, 'iphone') || str_contains($d, 'mobile')) {
            return 'bi-phone-fill';
        }
        return 'bi-laptop';
    }

    /**
     * Ubah teks User-Agent browser menjadi Jenis Perangkat (HP/Mobile, Tablet, PC/Laptop) beserta OS & Browser,
     * cth: "HP / Mobile (Android · Chrome)", "HP / Mobile (iPhone iOS · Safari)", "PC / Laptop (Windows · Edge)".
     */
    public static function detectDevice(?string $ua): string
    {
        $ua = trim((string) $ua);
        if ($ua === '') {
            return 'Tidak Diketahui';
        }

        // 1. Deteksi Jenis Perangkat & Sistem Operasi
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) || (preg_match('/Android/i', $ua) && ! preg_match('/Mobile/i', $ua))) {
            $os = preg_match('/iPad/i', $ua) ? 'iPadOS' : 'Android';
            $type = "Tablet ({$os}";
        } elseif (preg_match('/iPhone|iPod/i', $ua)) {
            $type = 'HP / Mobile (iPhone iOS';
        } elseif (preg_match('/Android/i', $ua)) {
            $type = 'HP / Mobile (Android';
        } elseif (preg_match('/Mobile|IEMobile|Opera Mini/i', $ua)) {
            $type = 'HP / Mobile (Web';
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $type = 'PC / Laptop (Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
            $type = 'PC / Laptop (Mac';
        } elseif (preg_match('/CrOS/i', $ua)) {
            $type = 'PC / Laptop (Chromebook';
        } elseif (preg_match('/Linux/i', $ua)) {
            $type = 'PC / Laptop (Linux';
        } else {
            $type = 'Perangkat Web (Lainnya';
        }

        // 2. Deteksi Browser
        if (preg_match('/EdgA?\/([0-9]+)/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/SamsungBrowser/i', $ua)) {
            $browser = 'Samsung Internet';
        } elseif (preg_match('/UCBrowser/i', $ua)) {
            $browser = 'UC Browser';
        } elseif (preg_match('/Firefox\/|FxiOS/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome\/|CriOS/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\//i', $ua)) {
            $browser = 'Safari';
        } else {
            $browser = '';
        }

        return $browser !== '' ? "{$type} · {$browser})" : "{$type})";
    }

    /**
     * Pastikan tabel activity_logs (beserta kolom device) sudah tersedia di database.
     * Aman dipanggil otomatis tanpa perlu akses SSH di hosting.
     */
    public static function ensureSchema(): void
    {
        try {
            if (! Schema::hasTable('activity_logs')) {
                Schema::create('activity_logs', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                    $table->unsignedBigInteger('customer_id')->nullable()->index();
                    $table->string('actor_name', 120);
                    $table->string('actor_role', 30)->index();
                    $table->string('category', 40)->index();
                    $table->string('action', 120);
                    $table->text('description')->nullable();
                    $table->string('subject_url', 255)->nullable();
                    $table->string('ip_address', 45)->nullable();
                    $table->string('device', 150)->nullable();
                    $table->timestamp('created_at')->useCurrent()->index();
                });

                self::backfillFromExistingData();
            } elseif (! Schema::hasColumn('activity_logs', 'device')) {
                Schema::table('activity_logs', function (Blueprint $table) {
                    $table->string('device', 150)->nullable()->after('ip_address');
                });
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Catat satu baris log aktivitas dengan aman (tidak menggagalkan proses utama jika terjadi error).
     */
    public static function record(array $attributes): ?self
    {
        try {
            self::ensureSchema();

            $device = $attributes['device'] ?? self::detectDevice(request()?->userAgent());

            return self::create([
                'user_id'     => $attributes['user_id'] ?? null,
                'customer_id' => $attributes['customer_id'] ?? null,
                'actor_name'  => mb_substr(trim((string) ($attributes['actor_name'] ?? 'Pengguna')), 0, 120),
                'actor_role'  => $attributes['actor_role'] ?? 'kasir',
                'category'    => $attributes['category'] ?? 'order',
                'action'      => mb_substr(trim((string) ($attributes['action'] ?? 'Aktivitas')), 0, 120),
                'description' => isset($attributes['description']) ? trim((string) $attributes['description']) : null,
                'subject_url' => $attributes['subject_url'] ?? null,
                'ip_address'  => $attributes['ip_address'] ?? request()?->ip(),
                'device'      => mb_substr(trim((string) $device), 0, 150),
                'created_at'  => $attributes['created_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Saat tabel activity_logs baru pertama kali dibuat di server yang sudah berjalan,
     * salin riwayat aktivitas yang sudah ada dari tabel orders, order_status_logs, payments, expenses, reservations, dan courier_tasks.
     */
    public static function backfillFromExistingData(): void
    {
        try {
            $users = Schema::hasTable('users') ? User::all()->keyBy('id') : collect();
            $customers = Schema::hasTable('customers') ? Customer::all()->keyBy('id') : collect();

            // 1. Nota yang pernah dibuat
            if (Schema::hasTable('orders')) {
                foreach (Order::orderBy('id')->limit(300)->get() as $o) {
                    $u = $users->get($o->user_id);
                    $c = $customers->get($o->customer_id);
                    self::create([
                        'user_id'     => $u?->id,
                        'customer_id' => $c?->id,
                        'actor_name'  => $u?->name ?? 'Staf',
                        'actor_role'  => $u?->roleKey() ?? 'kasir',
                        'category'    => 'order',
                        'action'      => 'Buat Nota Baru',
                        'description' => "Membuat nota {$o->invoice_number}" . ($c ? " untuk {$c->name}" : '') . ' sebesar Rp' . number_format((float) $o->final_amount, 0, ',', '.'),
                        'subject_url' => "/kasir/transaksi/{$o->id}",
                        'created_at'  => $o->order_date ?? $o->created_at ?? now(),
                    ]);
                }
            }

            // 2. Perubahan status cucian
            if (Schema::hasTable('order_status_logs') && Schema::hasTable('orders')) {
                $ordersById = Order::pluck('invoice_number', 'id');
                foreach (OrderStatusLog::orderBy('id')->limit(500)->get() as $log) {
                    if ($log->status === 'received' && $log->notes === 'Nota dibuat') {
                        continue; // Sudah diwakili oleh log Buat Nota Baru
                    }
                    $u = $users->get($log->user_id);
                    $inv = $ordersById[$log->order_id] ?? ('#' . $log->order_id);
                    $statusLabel = Order::STATUSES[$log->status] ?? $log->status;
                    self::create([
                        'user_id'     => $u?->id,
                        'actor_name'  => $u?->name ?? 'Staf',
                        'actor_role'  => $u?->roleKey() ?? 'operator',
                        'category'    => 'queue',
                        'action'      => 'Ubah Status Cucian',
                        'description' => "Mengubah status nota {$inv} menjadi \"{$statusLabel}\"" . ($log->notes ? " ({$log->notes})" : ''),
                        'subject_url' => "/kasir/transaksi/{$log->order_id}",
                        'created_at'  => $log->created_at ?? now(),
                    ]);
                }
            }

            // 3. Pembayaran nota
            if (Schema::hasTable('payments') && Schema::hasTable('orders')) {
                $ordersById = Order::pluck('invoice_number', 'id');
                foreach (Payment::orderBy('id')->limit(300)->get() as $p) {
                    $u = $users->get($p->user_id);
                    $inv = $ordersById[$p->order_id] ?? ('#' . $p->order_id);
                    $method = Order::PAYMENT_METHODS[$p->payment_method] ?? $p->payment_method;
                    self::create([
                        'user_id'     => $u?->id,
                        'actor_name'  => $u?->name ?? 'Staf',
                        'actor_role'  => $u?->roleKey() ?? 'kasir',
                        'category'    => 'order',
                        'action'      => 'Terima Pembayaran',
                        'description' => "Menerima pembayaran nota {$inv} sebesar Rp" . number_format((float) $p->amount, 0, ',', '.') . " via {$method}",
                        'subject_url' => "/kasir/transaksi/{$p->order_id}",
                        'created_at'  => $p->payment_date ?? $p->created_at ?? now(),
                    ]);
                }
            }

            // 4. Pengeluaran
            if (Schema::hasTable('expenses')) {
                foreach (Expense::orderBy('id')->limit(200)->get() as $ex) {
                    $u = $users->get($ex->user_id);
                    self::create([
                        'user_id'     => $u?->id,
                        'actor_name'  => $u?->name ?? 'Staf',
                        'actor_role'  => $u?->roleKey() ?? 'kasir',
                        'category'    => 'expense',
                        'action'      => 'Catat Pengeluaran',
                        'description' => 'Mencatat pengeluaran Rp' . number_format((float) $ex->amount, 0, ',', '.') . " — {$ex->description}",
                        'subject_url' => '/kasir/pengeluaran',
                        'created_at'  => $ex->created_at ?? now(),
                    ]);
                }
            }

            // 5. Reservasi pelanggan
            if (Schema::hasTable('reservations')) {
                foreach (Reservation::orderBy('id')->limit(200)->get() as $res) {
                    self::create([
                        'customer_id' => $res->customer_id,
                        'actor_name'  => $res->name ?: 'Pelanggan',
                        'actor_role'  => 'pelanggan',
                        'category'    => 'reservation',
                        'action'      => 'Buat Reservasi Jemput',
                        'description' => "Reservasi layanan {$res->service} untuk tanggal " . ($res->pickup_date?->format('d-m-Y') ?? '-'),
                        'subject_url' => '/kasir/reservasi',
                        'created_at'  => $res->created_at ?? now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}

