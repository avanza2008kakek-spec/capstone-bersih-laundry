<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Order;                                           // Nota transaksi
use App\Models\OrderStatusLog;                                  // Riwayat perubahan status cucian
use App\Support\Phone;                                          // Helper format nomor HP
use App\Support\Settings;                                       // Nama laundry (pesan WA)
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Validation\Rule;                                 // Aturan validasi

/**
 * Menu Antrean Cucian (owner, kasir, operator): daftar cucian per tahap dan tombol pindah tahap.
 * Tahap: Diterima → Dicuci → Dikeringkan → Disetrika → Siap diambil. "Sudah diambil" dicatat kasir di menu Transaksi.
 */
class QueueController extends Controller
{
    private const FLOW = ['received', 'washing', 'drying', 'ironing', 'ready_for_pickup'];

    // 1. Papan antrean: cucian yang belum diambil, dikelompokkan per tahap
    public function index(Settings $settings)
    {
        $orders = Order::with(['customer', 'items.service'])
            ->whereIn('order_status', self::FLOW)
            ->orderBy('estimate_date')                          // Yang paling dekat target selesai di atas
            ->get()
            ->groupBy('order_status');

        return response()->view('kasir.queue.index', [
            'columns'  => collect(self::FLOW)->mapWithKeys(fn ($s) => [$s => $orders->get($s, collect())]),
            'business' => $settings->businessName(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    // 2. Pindahkan cucian ke tahap lain (maju ke tahap berikutnya, atau langsung ke tahap tertentu)
    public function move(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::FLOW)]]);

        if (! in_array($order->order_status, self::FLOW, true)) {
            return back()->withErrors(['queue' => "Nota {$order->invoice_number} sudah diambil, tidak bisa diubah."]);
        }
        if ($data['status'] === $order->order_status) {
            return back();                                      // Tidak ada perubahan
        }

        // Mundur ke tahap sebelumnya (cth: tombol terpencet) dicatat jelas di riwayat status
        $from = $order->order_status;
        $mundur = array_search($data['status'], self::FLOW, true) < array_search($from, self::FLOW, true);

        $order->update(['order_status' => $data['status']]);
        OrderStatusLog::create([
            'order_id'   => $order->id,
            'user_id'    => $request->user()->id,
            'status'     => $data['status'],
            'notes'      => $mundur ? 'Dikembalikan dari ' . Order::STATUSES[$from] : null,
            'created_at' => now(),
        ]);

        return back()->with('status', $mundur
            ? "{$order->invoice_number} dikembalikan ke " . Order::STATUSES[$data['status']]
            : "{$order->invoice_number} → " . Order::STATUSES[$data['status']]);
    }

    /** Link WA "cucian sudah siap diambil" untuk pelanggan (dipakai di tampilan; kasir yang menekan Kirim). */
    public static function readyWaLink(Order $order, string $business): ?string
    {
        $phone = Phone::normalize($order->customer?->phone);
        if (! $phone) return null;

        $text = "Halo {$order->customer->name}, cucian Anda dengan nota *{$order->invoice_number}* di {$business} sudah *siap diambil*."
              . ($order->remaining() > 0 ? "\nSisa tagihan: Rp" . number_format($order->remaining(), 0, ',', '.') : '')
              . "\nTerima kasih!";

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
    }
}
