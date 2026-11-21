<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\CourierTask;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pencatat aktivitas seluruh user (Owner, Kasir, Operator, Kurir, dan Pelanggan).
 * Mencatat setiap aksi penting yang berhasil dijalankan ke tabel activity_logs.
 */
class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        // Simpan pelaku & target sebelum request dijalankan (penting untuk aksi logout atau hapus data)
        $beforeUser = $request->user();
        $beforeCustomerId = $request->hasSession() ? $request->session()->get('customer_id') : null;
        $beforeTarget = $this->captureBeforeTarget($request);

        $response = $next($request);

        try {
            $this->logIfApplicable($request, $response, $beforeUser, $beforeCustomerId, $beforeTarget);
        } catch (\Throwable $e) {
            // Jangan pernah menggagalkan request utama jika pencatatan log mengalami kendala
        }

        return $response;
    }

    private function captureBeforeTarget(Request $request): array
    {
        $info = [];
        try {
            if ($u = $request->route('user')) {
                $model = $u instanceof User ? $u : User::find($u);
                if ($model) {
                    $info['user_name'] = $model->name;
                    $info['user_role'] = $model->roleLabel();
                }
            }
            if ($s = $request->route('service')) {
                $model = $s instanceof Service ? $s : Service::find($s);
                if ($model) {
                    $info['service_name'] = $model->name;
                }
            }
            if ($c = $request->route('category')) {
                $model = $c instanceof ServiceCategory ? $c : ServiceCategory::find($c);
                if ($model) {
                    $info['category_name'] = $model->name;
                }
            }
            if ($e = $request->route('expense')) {
                $model = $e instanceof Expense ? $e : Expense::find($e);
                if ($model) {
                    $info['expense_desc'] = $model->description . ' (Rp' . number_format((float) $model->amount, 0, ',', '.') . ')';
                }
            }
        } catch (\Throwable $e) {
        }
        return $info;
    }

    private function logIfApplicable(
        Request $request,
        Response $response,
        ?User $beforeUser,
        mixed $beforeCustomerId,
        array $beforeTarget
    ): void {
        $routeName = (string) $request->route()?->getName();
        if ($routeName === '') {
            return;
        }

        // Hanya catat aksi perubahan data (POST/PUT/PATCH/DELETE) atau link magic login pelanggan
        if ($request->isMethod('GET') && $routeName !== 'register.magic') {
            return;
        }

        // Lewati jika gagal validasi atau status HTTP error
        if ($response->getStatusCode() >= 400) {
            return;
        }
        if ($request->hasSession() && $request->session()->has('errors')) {
            return;
        }

        $user = $request->user() ?? $beforeUser;
        $customerId = ($request->hasSession() ? $request->session()->get('customer_id') : null) ?? $beforeCustomerId;

        $entry = $this->buildEntry($request, $routeName, $user, $customerId, $beforeTarget);
        if (! $entry) {
            return;
        }

        // Tentukan identitas pelaku (Staff atau Pelanggan)
        if ($user) {
            $actorName = $user->name;
            $actorRole = $user->roleKey();
            $userId = $user->id;
            $custId = $entry['customer_id'] ?? null;
        } elseif ($customerId && ($cust = Customer::find($customerId))) {
            $actorName = $cust->name;
            $actorRole = 'pelanggan';
            $userId = null;
            $custId = $cust->id;
        } else {
            return;
        }

        ActivityLog::record([
            'user_id'     => $userId,
            'customer_id' => $custId,
            'actor_name'  => $actorName,
            'actor_role'  => $actorRole,
            'category'    => $entry['category'],
            'action'      => $entry['action'],
            'description' => $entry['description'] ?? null,
            'subject_url' => $entry['subject_url'] ?? null,
            'ip_address'  => $request->ip(),
            'device'      => ActivityLog::detectDevice($request->userAgent()),
        ]);
    }

    private function buildEntry(
        Request $request,
        string $routeName,
        ?User $user,
        mixed $customerId,
        array $beforeTarget
    ): ?array {
        return match ($routeName) {
            // 1. Login / Keluar Staff
            'kasir.login.attempt' => $user ? [
                'category'    => 'auth',
                'action'      => 'Masuk ke Staff',
                'description' => "Login berhasil ke halaman Staff ({$user->email})",
            ] : null,

            'kasir.logout' => [
                'category'    => 'auth',
                'action'      => 'Keluar dari Staff',
                'description' => 'Logout dari halaman Staff',
            ],

            'kasir.setup.store' => [
                'category'    => 'auth',
                'action'      => 'Buat Akun Owner Pertama',
                'description' => 'Membuat akun Owner pertama aplikasi',
            ],

            'kasir.owner-password.store' => [
                'category'    => 'auth',
                'action'      => 'Atur Password Owner',
                'description' => 'Mengatur ulang password akun Owner',
            ],

            // 2. Login / Daftar / Alamat Pelanggan
            'register.check', 'register.magic' => $customerId ? [
                'category'    => 'auth',
                'action'      => 'Masuk Akun Pelanggan',
                'description' => 'Pelanggan berhasil verifikasi kode WhatsApp & masuk ke website',
            ] : null,

            'logout' => [
                'category'    => 'auth',
                'action'      => 'Keluar Akun Pelanggan',
                'description' => 'Pelanggan keluar dari website',
            ],

            'address.update' => [
                'category'    => 'customer',
                'action'      => 'Ubah Alamat Pelanggan',
                'description' => 'Memperbarui alamat jemput & titik lokasi peta: ' . mb_substr((string) $request->input('address'), 0, 100),
            ],

            // 3. Reservasi
            'reservation.store' => [
                'category'    => 'reservation',
                'action'      => 'Buat Reservasi Jemput',
                'description' => 'Membuat reservasi layanan "' . $request->input('service') . '" untuk tgl ' . $request->input('pickup_date')
                    . ($request->filled('estimated_weight') ? ' (perkiraan berat: ' . $request->input('estimated_weight') . ')' : ''),
                'subject_url' => route('kasir.reservations.index', [], false),
            ],

            'kasir.reservations.update' => (function () use ($request) {
                $r = $request->route('reservation');
                $res = $r instanceof Reservation ? $r : Reservation::find($r);
                $statusLabel = Reservation::STATUSES[$request->input('status')] ?? $request->input('status');
                return [
                    'category'    => 'reservation',
                    'action'      => 'Ubah Status Reservasi',
                    'description' => 'Mengubah status reservasi ' . ($res?->name ?? '#' . $r) . " menjadi \"{$statusLabel}\"",
                    'subject_url' => route('kasir.reservations.index', [], false),
                ];
            })(),

            // 4. Transaksi Nota & Pembayaran
            'kasir.orders.store' => (function () use ($user) {
                $order = Order::with('customer')->where('user_id', $user?->id)->latest('id')->first();
                return [
                    'category'    => 'order',
                    'action'      => 'Buat Nota Baru',
                    'description' => $order
                        ? "Membuat nota {$order->invoice_number} untuk " . ($order->customer?->name ?? '-') . ' sebesar Rp' . number_format((float) $order->final_amount, 0, ',', '.')
                        : 'Membuat nota transaksi baru',
                    'subject_url' => $order ? route('kasir.orders.show', $order, false) : null,
                ];
            })(),

            'kasir.orders.pay' => (function () use ($request) {
                $o = $request->route('order');
                $order = $o instanceof Order ? $o : Order::find($o);
                $method = Order::PAYMENT_METHODS[$request->input('payment_method')] ?? $request->input('payment_method');
                $amt = number_format((float) $request->input('amount'), 0, ',', '.');
                return [
                    'category'    => 'order',
                    'action'      => 'Terima Pembayaran Nota',
                    'description' => 'Menerima pembayaran nota ' . ($order?->invoice_number ?? '') . " sebesar Rp{$amt} ({$method})",
                    'subject_url' => $order ? route('kasir.orders.show', $order, false) : null,
                ];
            })(),

            'kasir.orders.pickup' => (function () use ($request) {
                $o = $request->route('order');
                $order = $o instanceof Order ? $o : Order::find($o);
                return [
                    'category'    => 'order',
                    'action'      => 'Serahkan Cucian ke Pelanggan',
                    'description' => 'Menandai nota ' . ($order?->invoice_number ?? '') . ' sudah diambil pelanggan',
                    'subject_url' => $order ? route('kasir.orders.show', $order, false) : null,
                ];
            })(),

            // 5. Antrean Cucian
            'kasir.queue.move' => (function () use ($request) {
                $o = $request->route('order');
                $order = $o instanceof Order ? $o : Order::find($o);
                $statusLabel = Order::STATUSES[$order?->order_status] ?? $order?->order_status;
                return [
                    'category'    => 'queue',
                    'action'      => 'Ubah Status Antrean',
                    'description' => 'Mengubah status cucian nota ' . ($order?->invoice_number ?? '') . " menjadi \"{$statusLabel}\"",
                    'subject_url' => $order ? route('kasir.orders.show', $order, false) : null,
                ];
            })(),

            // 6. Tugas Kurir & Foto Kamera Serah Terima
            'kasir.courier-tasks.pickup' => (function () use ($request) {
                $r = $request->route('reservation');
                $res = $r instanceof Reservation ? $r : Reservation::find($r);
                $courier = User::find($request->input('courier_id'));
                return [
                    'category'    => 'courier',
                    'action'      => 'Tugaskan Kurir Jemput',
                    'description' => 'Menugaskan kurir ' . ($courier?->name ?? '-') . ' untuk menjemput cucian ' . ($res?->name ?? ''),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier-tasks.delivery' => (function () use ($request) {
                $o = $request->route('order');
                $order = $o instanceof Order ? $o : Order::find($o);
                $courier = User::find($request->input('courier_id'));
                $withPhoto = $request->hasFile('handover_photo') ? ' disertai foto kamera serah ke kurir' : '';
                return [
                    'category'    => 'courier',
                    'action'      => 'Tugaskan Kurir Antar',
                    'description' => 'Menugaskan kurir ' . ($courier?->name ?? '-') . ' mengantar nota ' . ($order?->invoice_number ?? '') . $withPhoto,
                    'subject_url' => $order ? route('kasir.orders.show', $order, false) : null,
                ];
            })(),

            'kasir.courier-tasks.reassign' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                $courier = User::find($request->input('courier_id'));
                return [
                    'category'    => 'courier',
                    'action'      => 'Ganti Kurir Tugas',
                    'description' => 'Mengalihkan tugas ' . ($task?->typeLabel() ?? 'kurir') . ' (' . ($task?->targetName() ?? '-') . ') ke kurir ' . ($courier?->name ?? '-'),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier-tasks.handover' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                $jenis = $task?->isPickup() ? 'Serah Terima dari Kurir ke Kasir' : 'Serah Terima dari Kasir ke Kurir';
                return [
                    'category'    => 'courier',
                    'action'      => 'Foto Kamera Serah Terima',
                    'description' => "Mengunggah foto kamera {$jenis} untuk pelanggan " . ($task?->targetName() ?? '-'),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier-tasks.cancel' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                return [
                    'category'    => 'courier',
                    'action'      => 'Batalkan Tugas Kurir',
                    'description' => 'Membatalkan tugas ' . ($task?->typeLabel() ?? 'kurir') . ' untuk ' . ($task?->targetName() ?? '-'),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier.start' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                return [
                    'category'    => 'courier',
                    'action'      => 'Kurir Berangkat',
                    'description' => 'Mulai berangkat ' . strtolower($task?->typeLabel() ?? 'tugas') . ' ke lokasi ' . ($task?->targetName() ?? '-'),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier.complete' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                $label = $task?->isPickup() ? 'Selesai Jemput Cucian (Foto Kamera)' : 'Selesai Antar Cucian (Foto Kamera)';
                return [
                    'category'    => 'courier',
                    'action'      => $label,
                    'description' => 'Menyelesaikan tugas ' . strtolower($task?->typeLabel() ?? '') . ' di tempat ' . ($task?->targetName() ?? '-') . ' beserta foto bukti kamera',
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier.handover' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                return [
                    'category'    => 'courier',
                    'action'      => 'Serahkan Jemputan ke Kasir (Foto Kamera)',
                    'description' => 'Kurir menyerahkan cucian jemputan ' . ($task?->targetName() ?? '-') . ' kepada kasir disertai foto bukti kamera',
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier.fail' => (function () use ($request) {
                $t = $request->route('task');
                $task = $t instanceof CourierTask ? $t : CourierTask::find($t);
                return [
                    'category'    => 'courier',
                    'action'      => 'Tugas Kurir Gagal',
                    'description' => 'Menandai tugas ' . strtolower($task?->typeLabel() ?? '') . ' (' . ($task?->targetName() ?? '-') . ') gagal: ' . $request->input('courier_notes'),
                    'subject_url' => route('kasir.courier-tasks.index', [], false),
                ];
            })(),

            'kasir.courier-photos.destroy-old' => [
                'category'    => 'courier',
                'action'      => 'Bersihkan Foto Bukti Lama',
                'description' => 'Menghapus foto bukti kurir yang lebih lama dari ' . $request->input('days') . ' hari',
                'subject_url' => route('kasir.courier-photos.index', [], false),
            ],

            'kasir.courier-tasks.photo.destroy' => [
                'category'    => 'courier',
                'action'      => 'Hapus Foto Bukti',
                'description' => 'Menghapus satu foto bukti pada tugas kurir #' . ($request->route('task') instanceof CourierTask ? $request->route('task')->id : $request->route('task')),
                'subject_url' => route('kasir.courier-photos.index', [], false),
            ],

            // 7. Data Pelanggan
            'kasir.customers.store' => [
                'category'    => 'customer',
                'action'      => 'Tambah Pelanggan Baru',
                'description' => 'Menambahkan pelanggan "' . $request->input('name') . '" (' . $request->input('phone') . ')',
                'subject_url' => route('kasir.customers.index', [], false),
            ],

            'kasir.customers.update' => (function () use ($request) {
                $c = $request->route('customer');
                $cust = $c instanceof Customer ? $c : Customer::find($c);
                return [
                    'category'    => 'customer',
                    'action'      => 'Ubah Data Pelanggan',
                    'description' => 'Memperbarui data pelanggan "' . ($cust?->name ?? $request->input('name')) . '"',
                    'subject_url' => $cust ? route('kasir.customers.show', $cust, false) : null,
                ];
            })(),

            // 8. Pengeluaran
            'kasir.expenses.store' => [
                'category'    => 'expense',
                'action'      => 'Catat Pengeluaran',
                'description' => 'Mencatat pengeluaran Rp' . number_format((float) $request->input('amount'), 0, ',', '.') . ' — ' . $request->input('description'),
                'subject_url' => route('kasir.expenses.index', [], false),
            ],

            'kasir.expenses.destroy' => [
                'category'    => 'expense',
                'action'      => 'Hapus Pengeluaran',
                'description' => 'Menghapus catatan pengeluaran: ' . ($beforeTarget['expense_desc'] ?? '#' . $request->route('expense')),
                'subject_url' => route('kasir.expenses.index', [], false),
            ],

            'kasir.expenses.categories.store' => [
                'category'    => 'expense',
                'action'      => 'Tambah Kategori Pengeluaran',
                'description' => 'Menambahkan kategori biaya "' . $request->input('name') . '"',
                'subject_url' => route('kasir.expenses.index', [], false),
            ],

            // 9. Layanan & Harga
            'kasir.services.store' => [
                'category'    => 'service',
                'action'      => 'Tambah Layanan Baru',
                'description' => 'Menambahkan layanan "' . $request->input('name') . '" (Rp' . number_format((float) $request->input('price'), 0, ',', '.') . '/' . $request->input('unit') . ')',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            'kasir.services.update' => [
                'category'    => 'service',
                'action'      => 'Ubah Layanan & Harga',
                'description' => 'Memperbarui layanan "' . $request->input('name') . '" (Rp' . number_format((float) $request->input('price'), 0, ',', '.') . '/' . $request->input('unit') . ')',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            'kasir.services.toggle' => (function () use ($request, $beforeTarget) {
                $s = $request->route('service');
                $svc = $s instanceof Service ? $s : Service::find($s);
                return [
                    'category'    => 'service',
                    'action'      => 'Ubah Status Layanan',
                    'description' => 'Mengubah status layanan "' . ($svc?->name ?? $beforeTarget['service_name'] ?? '-') . '" menjadi ' . ($svc?->is_active ? 'Aktif' : 'Nonaktif'),
                    'subject_url' => route('kasir.services.index', [], false),
                ];
            })(),

            'kasir.services.destroy' => [
                'category'    => 'service',
                'action'      => 'Hapus Layanan',
                'description' => 'Menghapus layanan "' . ($beforeTarget['service_name'] ?? '-') . '"',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            'kasir.services.categories.store' => [
                'category'    => 'service',
                'action'      => 'Tambah Kategori Layanan',
                'description' => 'Menambahkan kategori layanan "' . $request->input('name') . '"',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            'kasir.services.categories.update' => [
                'category'    => 'service',
                'action'      => 'Ubah Kategori Layanan',
                'description' => 'Mengubah nama kategori layanan menjadi "' . $request->input('name') . '"',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            'kasir.services.categories.destroy' => [
                'category'    => 'service',
                'action'      => 'Hapus Kategori Layanan',
                'description' => 'Menghapus kategori layanan "' . ($beforeTarget['category_name'] ?? '-') . '"',
                'subject_url' => route('kasir.services.index', [], false),
            ],

            // 10. Kelola Staf
            'kasir.staff.store' => [
                'category'    => 'staff',
                'action'      => 'Tambah Akun Staf',
                'description' => 'Membuat akun staf baru "' . $request->input('name') . '" dengan role ' . (User::ROLES[$request->input('role')] ?? $request->input('role')),
                'subject_url' => route('kasir.staff.index', [], false),
            ],

            'kasir.staff.update' => [
                'category'    => 'staff',
                'action'      => 'Ubah Akun Staf',
                'description' => 'Memperbarui data staf "' . $request->input('name') . '" (Role: ' . (User::ROLES[$request->input('role')] ?? $request->input('role')) . ')',
                'subject_url' => route('kasir.staff.index', [], false),
            ],

            'kasir.staff.destroy' => [
                'category'    => 'staff',
                'action'      => 'Hapus Akun Staf',
                'description' => 'Menghapus akun staf "' . ($beforeTarget['user_name'] ?? '-') . '" (' . ($beforeTarget['user_role'] ?? '-') . ')',
                'subject_url' => route('kasir.staff.index', [], false),
            ],

            // 11. Pengaturan Usaha, Rekening & QRIS
            'kasir.settings.update' => [
                'category'    => 'settings',
                'action'      => 'Ubah Nama Laundry',
                'description' => 'Mengubah nama laundry menjadi "' . $request->input('business_name') . '"',
                'subject_url' => route('kasir.settings', [], false),
            ],

            'kasir.settings.location' => [
                'category'    => 'settings',
                'action'      => 'Ubah Lokasi Laundry',
                'description' => 'Memperbarui alamat & titik lokasi Google Maps laundry',
                'subject_url' => route('kasir.settings', [], false) . '#lokasi',
            ],

            'kasir.settings.rates' => [
                'category'    => 'settings',
                'action'      => 'Ubah Tarif Ongkos Jemput',
                'description' => 'Memperbarui daftar tarif ongkos jemput per kilometer',
                'subject_url' => route('kasir.settings', [], false) . '#ongkos',
            ],

            'kasir.settings.payment' => [
                'category'    => 'settings',
                'action'      => 'Atur Rekening & Gambar QRIS',
                'description' => 'Memperbarui daftar rekening bank transfer' . ($request->hasFile('qris_image') ? ' dan mengganti gambar QRIS' : '') . ' untuk nota belum lunas',
                'subject_url' => route('kasir.settings', [], false) . '#pembayaran',
            ],

            'kasir.settings.qris.destroy' => [
                'category'    => 'settings',
                'action'      => 'Hapus Gambar QRIS',
                'description' => 'Menghapus gambar QRIS dari pengaturan nota',
                'subject_url' => route('kasir.settings', [], false) . '#pembayaran',
            ],

            'kasir.settings.theme' => [
                'category'    => 'settings',
                'action'      => 'Ganti Tema Website',
                'description' => 'Mengganti tema warna website menjadi "' . (\App\Support\Themes::ALL[$request->input('theme')]['name'] ?? $request->input('theme')) . '"',
                'subject_url' => route('kasir.settings', [], false) . '#tema',
            ],

            default => null,
        };
    }
}

