<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Reservation;                                     // Reservasi dari website
use App\Support\Phone;                                          // Helper format nomor HP (link WA)
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu Reservasi (semua staf): daftar reservasi jemput dari website + notifikasi reservasi baru.
 * Status: Baru → Dikonfirmasi → Sudah dijemput, atau Dibatalkan.
 */
class ReservationController extends Controller
{
    // Tab filter: "aktif" = baru + dikonfirmasi (yang masih perlu dijemput)
    private const TABS = [
        'aktif'     => ['new', 'confirmed'],
        'new'       => ['new'],
        'confirmed' => ['confirmed'],
        'picked_up' => ['picked_up'],
        'cancelled' => ['cancelled'],
    ];

    // 1. Daftar reservasi per tab
    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'aktif';

        // Fitur kurir aktif jika tabel courier_tasks & courier_photos sudah dibuat di server (database/sql/courier_tasks.sql)
        $courierReady = \Illuminate\Support\Facades\Schema::hasTable('courier_photos');
        if ($courierReady) {
            \App\Models\CourierPhoto::ensureSchema();
        }

        $reservations = Reservation::with(array_merge(['customer', 'handler'], $courierReady ? ['courierTasks.courier', 'courierTasks.photo', 'courierTasks.handoverPhoto'] : []))
            ->whereIn('status', self::TABS[$tab])
            // Yang aktif diurutkan dari tanggal jemput terdekat; arsip dari yang terbaru
            ->when($tab === 'aktif' || $tab === 'new' || $tab === 'confirmed',
                fn ($q) => $q->orderBy('pickup_date')->orderBy('id'),
                fn ($q) => $q->latest('id'))
            ->paginate(20)->withQueryString();

        $counts = Reservation::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        // Daftar kurir untuk form "Tugaskan jemput" (hanya owner & kasir yang boleh menugaskan)
        $couriers = $courierReady && $request->user()->hasRole('owner', 'kasir') ? CourierTaskController::couriers() : null;

        return view('kasir.reservations.index', compact('reservations', 'counts', 'tab', 'couriers'));
    }

    // 2. Ubah status (konfirmasi / sudah dijemput / batal / kembalikan ke baru)
    public function update(Request $request, Reservation $reservation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Reservation::STATUSES))]]);

        $reservation->update([
            'status'     => $data['status'],
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('status', "Reservasi {$reservation->customer?->name} → {$reservation->statusLabel()}.");
    }

    // 3. Cek reservasi baru (dipanggil otomatis tiap 30 detik oleh halaman kasir untuk notifikasi)
    public function check()
    {
        try {
            $latest = Reservation::with('customer')->where('status', 'new')->latest('id')->first();
            $count  = Reservation::where('status', 'new')->count();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['count' => 0, 'latest' => null]);    // Tabel belum dibuat
        }

        return response()->json([
            'count'  => $count,
            'latest' => $latest ? [
                'id'          => $latest->id,
                'name'        => $latest->customer?->name ?? 'Pelanggan',
                'service'     => $latest->service,
                'pickup_date' => $latest->pickup_date->format('d-m-Y'),
            ] : null,
        ]);
    }

    /** Link WhatsApp ke pelanggan (dipakai di tampilan). */
    public static function waLink(?string $phone): ?string
    {
        $intl = Phone::normalize((string) $phone);
        return $intl ? 'https://wa.me/' . $intl : null;
    }
}
