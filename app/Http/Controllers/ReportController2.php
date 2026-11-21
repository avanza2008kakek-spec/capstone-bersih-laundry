<?php

namespace App\Http\Controllers;

use App\Models\Order;                                           // Model transaksi pesanan
use App\Models\Payment;                                         // Model setoran kas masuk
use App\Models\Expense;                                         // Model kas keluar operasional
use Illuminate\Http\Request;                                    // Penangan data request HTTP
use Carbon\Carbon;                                              // Pustaka manipulasi tanggal dan waktu

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter tanggal dari form; jika kosong, gunakan rentang awal dan akhir bulan berjalan
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString()); // Tanggal awal filter
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());     // Tanggal akhir filter

        // --- 1. PERHITUNGAN ARUS KAS (CASH FLOW) ---
        $cashIn = Payment::whereBetween('payment_date', [       // Ambil total kas masuk dari setoran riil
            $startDate . ' 00:00:00', 
            $endDate . ' 23:59:59'
        ])->sum('amount');                                      // Hitung total penerimaan kas

        $cashOut = Expense::whereBetween('expense_date', [      // Ambil total kas keluar dari pengeluaran
            $startDate, 
            $endDate
        ])->sum('amount');                                      // Hitung total pengeluaran uang kas

        $netCashFlow = $cashIn - $cashOut;                      // Selisih arus kas bersih (surplus/defisit kas)

        // --- 2. PERHITUNGAN LABA RUGI SEDERHANA ---
        $revenue = Order::where('order_status', 'completed')     // Pendapatan dihitung dari pesanan yang sudah selesai dikerjakan
            ->whereBetween('order_date', [
                $startDate . ' 00:00:00', 
                $endDate . ' 23:59:59'
            ])
            ->sum('final_amount');                              // Total nilai bersih jasa cucian selesai

        $expensesByCategory = Expense::with('category')          // Ambil pengeluaran beserta relasi kategorinya
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get()
            ->groupBy('category.name')                          // Kelompokkan pengeluaran berdasarkan nama kategori biaya
            ->map(fn($group) => $group->sum('amount'));         // Jumlahkan nominal pengeluaran tiap-tiap kelompok

        $totalExpense = $expensesByCategory->sum();              // Akumulasi seluruh beban biaya operasional
        $netProfit = $revenue - $totalExpense;                  // Laba bersih operasional = Pendapatan Jasa - Total Beban

        // --- 3. REKAPITULASI PIUTANG USAHA (PELANGGAN BELUM LUNAS) ---
        $receivables = Order::with('customer')                   // Ambil pesanan beserta data identitas pelanggan
            ->whereIn('payment_status', ['unpaid', 'partial'])  // Filter hanya order yang belum lunas atau baru bayar DP
            ->get()
            ->map(function ($order) {
                $order->remaining = $order->final_amount - $order->paid_amount; // Hitung sisa tagihan per pelanggan
                return $order;
            });

        $totalReceivables = $receivables->sum('remaining');      // Total akumulasi seluruh piutang usaha yang belum tertagih

        // Kirim semua variabel hasil perhitungan ke halaman view Blade
        return view('reports.index', compact(
            'startDate', 'endDate',
            'cashIn', 'cashOut', 'netCashFlow',
            'revenue', 'expensesByCategory', 'totalExpense', 'netProfit',
            'receivables', 'totalReceivables'
        ));
    }
}