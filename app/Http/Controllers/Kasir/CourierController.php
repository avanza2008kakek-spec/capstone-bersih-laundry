<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CourierPhoto;                                    // Foto bukti (disimpan di database)
use App\Models\CourierTask;                                     // Tugas kurir
use App\Support\PhotoCompressor;                                // Perkecil foto sebelum disimpan
use App\Models\Order;                                           // Nota (status & pembayaran saat diantar)
use App\Models\OrderStatusLog;                                  // Riwayat status cucian
use App\Models\Payment;                                         // Pembayaran yang diterima kurir saat antar
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Menu "Tugas Saya" untuk kurir: daftar tugas jemput/antar dari kasir, tombol berangkat,
 * selesai (wajib foto bukti) atau gagal (wajib alasan).
 */
class CourierController extends Controller
{
    // 1. Daftar tugas: yang berjalan (urut tanggal) + jemputan yang perlu diserahkan ke kasir + riwayat 7 hari terakhir
    public function index(Request $request)
    {
        CourierPhoto::ensureSchema();
        $me = $request->user()->id;

        return view('kasir.courier.index', [
            'active'  => CourierTask::with(['customer', 'order', 'reservation', 'assigner', 'handoverPhoto'])->active()->where('courier_id', $me)
                ->orderBy('scheduled_date')->orderBy('id')->get(),
            'pendingHandover' => CourierTask::with(['customer', 'reservation', 'photo', 'handoverPhoto'])
                ->where('courier_id', $me)->where('type', 'pickup')->where('status', 'done')
                ->whereDoesntHave('handoverPhoto')
                ->latest('finished_at')->get(),
            'history' => CourierTask::with(['customer', 'order', 'photo', 'handoverPhoto'])->where('courier_id', $me)->whereIn('status', ['done', 'failed', 'cancelled'])
                ->where('updated_at', '>=', now()->subDays(7))->latest('updated_at')->limit(30)->get(),
        ]);
    }

    // 2. Kurir berangkat (status "Dalam perjalanan")
    public function start(Request $request, CourierTask $task)
    {
        $this->authorizeTask($request, $task);
        if ($task->status === 'assigned') {
            $task->update(['status' => 'on_the_way', 'started_at' => now()]);
        }
        return back()->with('status', 'Hati-hati di jalan! Tugas ' . strtolower($task->typeLabel()) . ' ' . ($task->customer?->name ?? '') . ' dalam perjalanan.');
    }

    // 3. Selesai: wajib foto bukti. Antar + masih ada tagihan → kurir mencatat pembayaran dari pelanggan.
    public function complete(Request $request, CourierTask $task)
    {
        $this->authorizeTask($request, $task);
        $order = $task->type === 'delivery' ? $task->order : null;
        $remaining = $order ? $order->remaining() : 0;

        $data = $request->validate([
            'photo'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],   // Maks. 8 MB
            'courier_notes'  => ['nullable', 'string', 'max:255'],
        ] + ($remaining > 0 ? [
            'paid'           => ['accepted'],                                                     // Centang "uang sudah diterima"
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
        ] : []), [
            'photo.required' => 'Ambil foto bukti dulu.',
            'photo.image'    => 'File bukti harus berupa foto.',
            'photo.max'      => 'Ukuran foto maksimal 8 MB.',
            'paid.accepted'  => 'Tagih sisa pembayaran Rp' . number_format($remaining, 0, ',', '.') . ' dulu, lalu centang kotak "uang sudah diterima".',
        ], ['payment_method' => 'cara bayar']);

        // Perkecil foto (maks. 1024 px, ≤ 300 KB) lalu simpan langsung di database
        CourierPhoto::ensureSchema();
        $photo = PhotoCompressor::compress($request->file('photo'));
        if (! $photo) {
            return back()->withErrors(['photo' => 'Foto terlalu besar. Ambil ulang fotonya (jangan pakai mode HDR/resolusi penuh), lalu kirim lagi.']);
        }

        DB::transaction(function () use ($task, $order, $remaining, $data, $photo, $request) {
            $task->update([
                'status'        => 'done',
                'courier_notes' => $data['courier_notes'] ?? null,
                'started_at'    => $task->started_at ?? now(),
                'finished_at'   => now(),
            ]);
            CourierPhoto::updateOrCreate(
                ['courier_task_id' => $task->id, 'kind' => CourierPhoto::KIND_CUSTOMER],
                $photo
            );

            if ($task->type === 'pickup' && $task->reservation) {
                // Cucian sudah dijemput → reservasi "Sudah dijemput", kasir tinggal membuat nota
                $task->reservation->update(['status' => 'picked_up', 'handled_by' => $request->user()->id, 'handled_at' => now()]);
            }

            if ($order) {
                if ($remaining > 0) {
                    // Pelunasan diterima kurir saat menyerahkan cucian
                    Payment::create([
                        'order_id' => $order->id, 'user_id' => $request->user()->id, 'payment_date' => now(),
                        'amount' => $remaining, 'payment_method' => $data['payment_method'], 'payment_type' => 'settlement',
                        'notes' => 'Diterima kurir saat antar',
                    ]);
                    $order->paid_amount = round((float) $order->paid_amount + $remaining, 2);
                    $order->payment_status = Order::paymentStatusFor((float) $order->paid_amount, (float) $order->final_amount);
                }
                $order->order_status = 'completed';
                $order->completion_date = now();
                $order->save();
                OrderStatusLog::create([
                    'order_id' => $order->id, 'user_id' => $request->user()->id, 'status' => 'completed',
                    'notes' => 'Diantar kurir ' . $request->user()->name, 'created_at' => now(),
                ]);
            }
        });

        return redirect()->route('kasir.courier.index')
            ->with('status', 'Tugas ' . strtolower($task->typeLabel()) . ' ' . ($task->customer?->name ?? '') . ' selesai. Terima kasih!');
    }

    // 3b. Serahkan cucian hasil jemputan ke kasir di outlet (wajib foto kamera)
    public function handoverToKasir(Request $request, CourierTask $task)
    {
        abort_unless((int) $task->courier_id === (int) $request->user()->id, 403, 'Ini bukan tugas Anda.');
        abort_unless($task->type === 'pickup' && $task->status === 'done', 422, 'Tugas jemput belum selesai dijemput.');

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'photo.required' => 'Ambil foto bukti penyerahan ke kasir dulu.',
            'photo.image'    => 'File bukti harus berupa foto.',
            'photo.max'      => 'Ukuran foto maksimal 8 MB.',
        ]);

        CourierPhoto::ensureSchema();
        $photo = PhotoCompressor::compress($request->file('photo'));
        if (! $photo) {
            return back()->withErrors(['photo' => 'Foto terlalu besar. Ambil ulang fotonya (jangan pakai mode HDR/resolusi penuh), lalu kirim lagi.']);
        }

        DB::transaction(function () use ($task, $photo) {
            CourierPhoto::updateOrCreate(
                ['courier_task_id' => $task->id, 'kind' => CourierPhoto::KIND_HANDOVER],
                $photo
            );
            $task->update(['handover_at' => now()]);
        });

        return redirect()->route('kasir.courier.index')
            ->with('status', 'Cucian ' . ($task->customer?->name ?? '') . ' berhasil diserahkan ke kasir beserta foto bukti.');
    }

    // 4. Gagal (pelanggan tidak ada di rumah, alamat tidak ketemu, dll.) → kasir bisa menugaskan ulang
    public function fail(Request $request, CourierTask $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']],
            ['reason.required' => 'Tulis alasan gagal.', 'reason.min' => 'Alasan terlalu pendek.']);

        $task->update(['status' => 'failed', 'courier_notes' => $data['reason'], 'finished_at' => now()]);

        return redirect()->route('kasir.courier.index')->with('status', 'Tugas ditandai gagal. Kasir akan menindaklanjuti.');
    }

    // 5. Cek tugas baru (dipanggil otomatis tiap 30 detik untuk notifikasi kurir)
    public function check(Request $request)
    {
        try {
            $q = CourierTask::with('customer')->active()->where('courier_id', $request->user()->id);
            $count = (clone $q)->count();
            $latest = $q->latest('id')->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['count' => 0, 'latest' => null]);
        }

        return response()->json([
            'count'  => $count,
            'latest' => $latest ? [
                'id'          => $latest->id,
                'name'        => $latest->customer?->name ?? 'Pelanggan',
                'service'     => $latest->typeLabel(),
                'pickup_date' => $latest->scheduled_date->format('d-m-Y'),
            ] : null,
        ]);
    }

    // Hanya kurir yang ditugaskan, dan hanya tugas yang masih berjalan
    private function authorizeTask(Request $request, CourierTask $task): void
    {
        abort_unless((int) $task->courier_id === (int) $request->user()->id, 403, 'Ini bukan tugas Anda.');
        abort_unless($task->isActive(), 422, 'Tugas ini sudah ' . strtolower($task->statusLabel()) . '.');
    }
}
