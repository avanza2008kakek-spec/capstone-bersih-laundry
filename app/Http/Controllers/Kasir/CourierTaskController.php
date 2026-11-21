<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CourierPhoto;                                    // Foto bukti (disimpan di database)
use App\Models\CourierTask;                                     // Tugas kurir
use App\Models\Order;                                           // Nota (tugas antar)
use App\Models\Reservation;                                     // Reservasi (tugas jemput)
use App\Models\User;                                            // Akun kurir
use App\Support\PhotoCompressor;                                // Perkecil foto sebelum disimpan
use App\Support\Settings;                                       // Nama laundry (pesan WA)
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu "Tugas Kurir" untuk owner & kasir: menugaskan kurir menjemput (dari reservasi) dan mengantar (dari nota),
 * memantau status, mengganti kurir, membatalkan, dan melihat foto bukti.
 */
class CourierTaskController extends Controller
{
    private const TABS = [
        'aktif'     => CourierTask::ACTIVE,
        'done'      => ['done'],
        'failed'    => ['failed'],
        'cancelled' => ['cancelled'],
    ];

    // 1. Daftar semua tugas kurir
    public function index(Request $request)
    {
        CourierPhoto::ensureSchema();
        $tab = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'aktif';

        $tasks = CourierTask::with(['customer', 'courier', 'order', 'reservation', 'photo', 'handoverPhoto'])
            ->whereIn('status', self::TABS[$tab])
            ->when($tab === 'aktif', fn ($q) => $q->orderBy('scheduled_date')->orderBy('id'), fn ($q) => $q->latest('id'))
            ->paginate(20)->withQueryString();

        return view('kasir.courier-tasks.index', [
            'tasks'    => $tasks,
            'tab'      => $tab,
            'counts'   => CourierTask::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'couriers' => self::couriers(),
        ]);
    }

    // 2. Tugaskan kurir menjemput cucian dari reservasi
    public function assignPickup(Request $request, Reservation $reservation)
    {
        if (! in_array($reservation->status, ['new', 'confirmed'], true)) {
            return back()->withErrors(['courier' => 'Reservasi ini sudah ' . strtolower($reservation->statusLabel()) . '.']);
        }
        if ($reservation->courierTasks()->active()->exists()) {
            return back()->withErrors(['courier' => 'Reservasi ini sudah punya kurir. Ganti kurirnya di menu Tugas Kurir.']);
        }
        $data = $this->validateAssign($request, false);

        $task = CourierTask::create([
            'type'           => 'pickup',
            'reservation_id' => $reservation->id,
            'customer_id'    => $reservation->customer_id,
            'courier_id'     => $data['courier_id'],
            'assigned_by'    => $request->user()->id,
            'scheduled_date' => $reservation->pickup_date,
            'address'        => $reservation->address,
            'maps_url'       => $reservation->maps_url,
            'notes'          => $data['notes'] ?? null,
        ]);
        // Reservasi otomatis dikonfirmasi saat kurir ditugaskan
        $reservation->update(['status' => 'confirmed', 'handled_by' => $request->user()->id, 'handled_at' => now()]);

        return $this->backWithWa($task, 'Kurir ' . User::find($data['courier_id'])->name . ' ditugaskan menjemput cucian ' . ($reservation->customer?->name ?? '') . '.');
    }

    // 3. Tugaskan kurir mengantar cucian yang sudah siap (dari halaman nota)
    public function assignDelivery(Request $request, Order $order)
    {
        if ($order->order_status !== 'ready_for_pickup') {
            return back()->withErrors(['courier' => 'Cucian belum berstatus "Siap diambil".']);
        }
        if ($order->courierTasks()->active()->exists()) {
            return back()->withErrors(['courier' => 'Nota ini sudah punya kurir pengantar.']);
        }
        $customer = $order->customer;
        $data = $this->validateAssign($request, true);
        $address = trim((string) ($data['address'] ?? '')) ?: $customer?->address;
        if (! $address) {
            return back()->withInput()->withErrors(['address' => 'Isi alamat antar (pelanggan belum punya alamat).']);
        }

        CourierPhoto::ensureSchema();
        $handoverPhoto = null;
        if ($request->hasFile('handover_photo')) {
            $handoverPhoto = PhotoCompressor::compress($request->file('handover_photo'));
            if (! $handoverPhoto) {
                return back()->withInput()->withErrors(['handover_photo' => 'Foto terlalu besar. Ambil ulang fotonya, lalu kirim lagi.']);
            }
        }

        $task = CourierTask::create([
            'type'           => 'delivery',
            'order_id'       => $order->id,
            'customer_id'    => $order->customer_id,
            'courier_id'     => $data['courier_id'],
            'assigned_by'    => $request->user()->id,
            'scheduled_date' => $data['scheduled_date'],
            'address'        => $address,
            'maps_url'       => $address === $customer?->address ? $customer?->maps_url : null,
            'notes'          => $data['notes'] ?? null,
            'handover_at'    => $handoverPhoto ? now() : null,
        ]);

        if ($handoverPhoto) {
            CourierPhoto::updateOrCreate(
                ['courier_task_id' => $task->id, 'kind' => CourierPhoto::KIND_HANDOVER],
                $handoverPhoto
            );
        }

        return $this->backWithWa($task, 'Kurir ' . User::find($data['courier_id'])->name . " ditugaskan mengantar nota {$order->invoice_number}.");
    }

    // 3b. Foto serah terima antara kasir & kurir (kasir menyerahkan ke kurir untuk diantar, atau menerima jemputan dari kurir)
    public function handover(Request $request, CourierTask $task)
    {
        abort_if($task->status === 'cancelled', 422, 'Tugas ini sudah dibatalkan.');

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'photo.required' => 'Ambil foto bukti serah terima dulu.',
            'photo.image'    => 'File bukti harus berupa foto.',
            'photo.max'      => 'Ukuran foto maksimal 8 MB.',
        ]);

        CourierPhoto::ensureSchema();
        $photo = PhotoCompressor::compress($request->file('photo'));
        if (! $photo) {
            return back()->withErrors(['photo' => 'Foto terlalu besar. Ambil ulang fotonya (jangan pakai mode HDR/resolusi penuh), lalu kirim lagi.']);
        }

        CourierPhoto::updateOrCreate(
            ['courier_task_id' => $task->id, 'kind' => CourierPhoto::KIND_HANDOVER],
            $photo
        );
        $task->update(['handover_at' => now()]);

        $msg = $task->type === 'delivery'
            ? 'Foto penyerahan cucian ke kurir ' . ($task->courier?->name ?? '') . ' berhasil disimpan.'
            : 'Foto penerimaan cucian jemputan dari kurir ' . ($task->courier?->name ?? '') . ' berhasil disimpan.';

        return back()->with('status', $msg);
    }

    // 4. Ganti kurir (tugas yang masih berjalan)
    public function reassign(Request $request, CourierTask $task)
    {
        abort_unless($task->isActive(), 422, 'Tugas ini sudah tidak berjalan.');
        $data = $request->validate(['courier_id' => ['required', Rule::exists('users', 'id')->where('role', User::KURIR)]],
            [], ['courier_id' => 'kurir']);

        $task->update(['courier_id' => $data['courier_id'], 'status' => 'assigned', 'started_at' => null]);

        return $this->backWithWa($task->fresh(), 'Kurir diganti menjadi ' . $task->courier()->first()->name . '.');
    }

    // 5. Batalkan tugas
    public function cancel(CourierTask $task)
    {
        abort_unless($task->isActive(), 422, 'Tugas ini sudah tidak berjalan.');
        $task->update(['status' => 'cancelled', 'finished_at' => now()]);

        return back()->with('status', 'Tugas ' . strtolower($task->typeLabel()) . ' dibatalkan.');
    }

    // 6. Tampilkan foto bukti dari database (hanya owner, kasir, dan kurir yang bersangkutan)
    public function photo(Request $request, CourierTask $task)
    {
        $user = $request->user();
        abort_unless($user->hasRole(User::OWNER, User::KASIR) || (int) $task->courier_id === (int) $user->id, 403);
        CourierPhoto::ensureSchema();

        $kind = $request->query('kind') === CourierPhoto::KIND_HANDOVER
            ? CourierPhoto::KIND_HANDOVER
            : CourierPhoto::KIND_CUSTOMER;

        $photo = CourierPhoto::where('courier_task_id', $task->id)
            ->where(fn ($q) => $kind === CourierPhoto::KIND_CUSTOMER
                ? $q->where('kind', CourierPhoto::KIND_CUSTOMER)->orWhereNull('kind')
                : $q->where('kind', $kind))
            ->first();                                                         // Di sini baru isi foto dibaca
        abort_unless($photo, 404);

        $data = is_resource($photo->data) ? stream_get_contents($photo->data) : $photo->data;
        return response($data, 200, [
            'Content-Type'           => $photo->mime,
            'Content-Length'         => strlen($data),
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // 7. Menu owner "Foto Bukti": pemakaian database + daftar foto untuk dihapus
    public function photos()
    {
        CourierPhoto::ensureSchema();
        return view('kasir.courier-tasks.photos', [
            'photos' => CourierPhoto::select(CourierPhoto::META)->with('task.customer', 'task.courier')->latest('id')->paginate(24),
            'total'  => ['count' => CourierPhoto::count(), 'bytes' => (int) CourierPhoto::sum('size')],
            'oldest' => CourierPhoto::min('created_at'),
        ]);
    }

    // 8. Hapus satu foto (tugasnya tetap tercatat)
    public function destroyPhoto(Request $request, CourierTask $task)
    {
        CourierPhoto::ensureSchema();
        $kind = $request->input('kind') ?? $request->query('kind');
        $q = CourierPhoto::where('courier_task_id', $task->id);
        if ($kind === CourierPhoto::KIND_HANDOVER) {
            $q->where('kind', CourierPhoto::KIND_HANDOVER);
        } elseif ($kind === CourierPhoto::KIND_CUSTOMER) {
            $q->where(fn ($w) => $w->where('kind', CourierPhoto::KIND_CUSTOMER)->orWhereNull('kind'));
        }
        $deleted = $q->delete();
        return back()->with('status', $deleted ? 'Foto bukti dihapus.' : 'Foto sudah tidak ada.');
    }

    // 9. Hapus semua foto yang lebih lama dari N hari (melegakan database)
    public function destroyOldPhotos(Request $request)
    {
        $data = $request->validate(['days' => ['required', 'integer', Rule::in([7, 30, 60, 90, 180])]]);
        $q = CourierPhoto::where('created_at', '<', now()->subDays($data['days']));
        $bytes = (int) (clone $q)->sum('size');
        $count = $q->delete();

        return back()->with('status', $count
            ? "{$count} foto lebih lama dari {$data['days']} hari dihapus (" . CourierPhoto::humanSize($bytes) . ' dibebaskan).'
            : "Tidak ada foto yang lebih lama dari {$data['days']} hari.");
    }

    // ---------- Fungsi bantu ----------

    /**
     * Kembali ke halaman sebelumnya + tombol "Kirim WA ke kurir" (link wa.me berisi pesan tugas).
     * Kasir tinggal mengetuk tombol lalu menekan Kirim di WhatsApp.
     */
    private function backWithWa(CourierTask $task, string $status)
    {
        $task->load(['courier', 'customer', 'reservation', 'order']);
        return back()->with('status', $status)->with('courier_wa', [
            'name' => $task->courier?->name,
            'link' => $task->waLink(app(Settings::class)->businessName()),   // null = No. HP kurir belum diisi
        ]);
    }

    /** Daftar akun kurir untuk pilihan penugasan. */
    public static function couriers()
    {
        return User::where('role', User::KURIR)->orderBy('name')->get(['id', 'name', 'phone']);
    }

    private function validateAssign(Request $request, bool $delivery): array
    {
        return $request->validate([
            'courier_id'     => ['required', Rule::exists('users', 'id')->where('role', User::KURIR)],
            'notes'          => ['nullable', 'string', 'max:255'],
        ] + ($delivery ? [
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'address'        => ['nullable', 'string', 'max:500'],
            'handover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ] : []), [
            'courier_id.required' => 'Pilih kurir dulu.',
            'courier_id.exists'   => 'Kurir tidak ditemukan. Buat akun kurir di menu Kelola Staf.',
            'handover_photo.image' => 'File bukti serah ke kurir harus berupa foto.',
            'handover_photo.max'   => 'Ukuran foto maksimal 8 MB.',
        ], ['scheduled_date' => 'tanggal antar', 'notes' => 'pesan untuk kurir', 'address' => 'alamat antar', 'handover_photo' => 'foto serah ke kurir']);
    }
}
