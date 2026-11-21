<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Expense;                                         // Pengeluaran
use App\Models\Order;                                           // Nota transaksi
use App\Models\Payment;                                         // Pembayaran masuk
use Illuminate\Http\Request;

/** Dashboard Area Kasir: ringkasan hari ini (isi menyesuaikan role). */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $today = now()->toDateString();

        // Jumlah cucian per tahap antrean (dipakai semua role)
        $queue = Order::whereIn('order_status', ['received', 'washing', 'drying', 'ironing', 'ready_for_pickup'])
            ->selectRaw('order_status, COUNT(*) as total')->groupBy('order_status')->pluck('total', 'order_status');

        $stats = ['queue' => $queue];

        // Kurir: jumlah tugas miliknya (berjalan & selesai hari ini)
        if ($request->user()->hasRole('kurir')) {
            $me = $request->user()->id;
            $stats['courier'] = [
                'active'     => \App\Models\CourierTask::activeCountFor($me),
                'done_today' => rescue(fn () => \App\Models\CourierTask::where('courier_id', $me)->where('status', 'done')
                    ->whereDate('finished_at', $today)->count(), 0, false),
            ];
        }

        // Angka uang hanya untuk owner & kasir (operator tidak melihat keuangan)
        if ($request->user()->hasRole('owner', 'kasir')) {
            $stats += [
                'orders_today'  => Order::whereDate('order_date', $today)->count(),
                'cash_in_today' => Payment::whereDate('payment_date', $today)
                    ->selectRaw('payment_method, SUM(amount) as total')->groupBy('payment_method')->pluck('total', 'payment_method'),
                'expense_today' => (float) Expense::whereDate('expense_date', $today)->sum('amount'),
                'unpaid_total'  => (float) Order::whereIn('payment_status', ['unpaid', 'partial'])->selectRaw('SUM(final_amount - paid_amount) as sisa')->value('sisa'),
            ];
        }

        return view('kasir.dashboard', compact('stats'));
    }
}
