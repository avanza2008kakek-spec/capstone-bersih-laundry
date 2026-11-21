<?php

namespace App\Http\Controllers;

use App\Models\Order;                                           // Model transaksi pesanan
use App\Models\Payment;                                         // Model setoran kas masuk
use App\Models\Expense;                                         // Model kas keluar operasional
use Illuminate\Http\Request;                                    // Penangan data request HTTP
use Carbon\Carbon;                                              // Pustaka manipulasi tanggal dan waktu

/**
 * Laporan Keuangan & Operasional Laundry (khusus role Owner).
 * Menyajikan ringkasan Arus Kas (Cash In / Out), Laba Rugi Operasional,
 * breakdown Beban per Kategori, Metode Pembayaran, dan Piutang Usaha.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter tanggal dari form; jika kosong, gunakan rentang awal dan akhir bulan berjalan
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Jika tanggal awal lebih besar dari tanggal akhir, tukar posisinya agar query valid
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime   = $endDate . ' 23:59:59';

        // --- 1. PERHITUNGAN ARUS KAS (CASH FLOW) ---
        // Kas masuk dari setoran riil pada rentang tanggal
        $cashIn = (float) Payment::whereBetween('payment_date', [$startDateTime, $endDateTime])->sum('amount');

        // Kas keluar dari biaya operasional pada rentang tanggal
        $cashOut = (float) Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');

        // Arus kas bersih (surplus/defisit kas)
        $netCashFlow = $cashIn - $cashOut;

        // Breakdown kas masuk berdasarkan metode pembayaran (cash, transfer, qris)
        $paymentsByMethod = Payment::whereBetween('payment_date', [$startDateTime, $endDateTime])
            ->selectRaw('payment_method, sum(amount) as total, count(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // --- 2. PERHITUNGAN LABA RUGI OPERASIONAL ---
        // Pendapatan dihitung dari pesanan yang sudah selesai dikerjakan dalam periode ini
        $revenue = (float) Order::where('order_status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime])
            ->sum('final_amount');

        // Total pesanan dibuat dan pesanan selesai dalam periode
        $totalOrdersInPeriod = Order::whereBetween('order_date', [$startDateTime, $endDateTime])->count();
        $completedOrdersCount = Order::where('order_status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime])
            ->count();

        // Pengeluaran dikelompokkan berdasarkan kategori biaya, diurutkan dari yang terbesar
        $expensesByCategory = Expense::with('category')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($e) => $e->category->name ?? 'Lain-lain')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $totalExpense = (float) $expensesByCategory->sum();
        $netProfit    = $revenue - $totalExpense; // Laba/rugi bersih operasional

        // --- 3. REKAPITULASI PIUTANG USAHA (PELANGGAN BELUM LUNAS) ---
        $receivables = Order::with('customer')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($order) {
                $order->remaining = max(0, (float) $order->final_amount - (float) $order->paid_amount);
                return $order;
            })
            ->filter(fn ($order) => $order->remaining > 0)
            ->values();

        $totalReceivables = (float) $receivables->sum('remaining');

        return view('reports.index', compact(
            'startDate', 'endDate',
            'cashIn', 'cashOut', 'netCashFlow',
            'revenue', 'expensesByCategory', 'totalExpense', 'netProfit',
            'receivables', 'totalReceivables',
            'paymentsByMethod', 'totalOrdersInPeriod', 'completedOrdersCount'
        ));
    }
}