<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto bukti jemput/antar kurir, disimpan langsung di database (kolom "data").
 * Untuk daftar/tampilan cukup ambil kolom kecilnya saja (META), isi foto hanya dibaca saat foto dibuka.
 */
class CourierPhoto extends Model
{
    public const KIND_CUSTOMER = 'customer';                     // Foto di tempat pelanggan (saat jemput / saat antar)
    public const KIND_HANDOVER = 'handover';                     // Foto serah terima di outlet (kurir → kasir / kasir → kurir)

    public const META = ['id', 'courier_task_id', 'kind', 'mime', 'size', 'created_at'];   // Kolom tanpa isi foto

    protected $fillable = ['courier_task_id', 'kind', 'mime', 'size', 'data'];

    protected $hidden = ['data'];                                // Jangan ikut terkirim saat diubah ke JSON

    public function task(): BelongsTo
    {
        return $this->belongsTo(CourierTask::class, 'courier_task_id');
    }

    /** Label jenis foto untuk ditampilkan di daftar Foto Bukti. */
    public function kindLabel(?CourierTask $task = null): string
    {
        $task ??= $this->relationLoaded('task') ? $this->task : null;
        if (($this->kind ?? self::KIND_CUSTOMER) === self::KIND_HANDOVER) {
            return match ($task?->type) {
                'pickup'   => 'Serah ke Kasir',
                'delivery' => 'Serah ke Kurir',
                default    => 'Serah Terima',
            };
        }
        return $task?->typeLabel() ?? 'Bukti';
    }

    /**
     * Pastikan kolom kind (di courier_photos) & handover_at (di courier_tasks) sudah ada di database server.
     * Aman dipanggil otomatis tanpa perlu akses SSH di hosting.
     */
    public static function ensureSchema(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('courier_tasks') && ! \Illuminate\Support\Facades\Schema::hasColumn('courier_tasks', 'handover_at')) {
                \Illuminate\Support\Facades\Schema::table('courier_tasks', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->timestamp('handover_at')->nullable()->after('finished_at');
                });
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('courier_photos') && ! \Illuminate\Support\Facades\Schema::hasColumn('courier_photos', 'kind')) {
                \Illuminate\Support\Facades\Schema::table('courier_photos', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('kind', 20)->default(self::KIND_CUSTOMER)->after('courier_task_id');
                });
                try {
                    \Illuminate\Support\Facades\Schema::table('courier_photos', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->index('courier_task_id', 'courier_photos_task_id_idx');
                        $table->dropUnique('courier_photos_courier_task_id_unique');
                        $table->unique(['courier_task_id', 'kind'], 'courier_photos_task_kind_unique');
                    });
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /** Ukuran dalam teks, cth: "128 KB" / "1,2 MB". */
    public static function humanSize(int|float $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
            : number_format(max(1, round($bytes / 1024)), 0, ',', '.') . ' KB';
    }
}
