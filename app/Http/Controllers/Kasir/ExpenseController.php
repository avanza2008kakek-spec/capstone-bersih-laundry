<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Expense;                                         // Pengeluaran (kas keluar)
use App\Models\ExpenseCategory;                                 // Kategori biaya
use Illuminate\Http\Request;                                    // Penangan request HTTP

/**
 * Menu Pengeluaran: kasir & owner mencatat kas keluar; menghapus catatan & menambah kategori khusus owner.
 */
class ExpenseController extends Controller
{
    // 1. Form catat + daftar pengeluaran pada tanggal terpilih (default hari ini)
    public function index(Request $request)
    {
        $date = $request->date('tanggal')?->toDateString() ?? now()->toDateString();

        $expenses = Expense::with(['category', 'user'])
            ->whereDate('expense_date', $date)
            ->orderByDesc('id')->get();

        return view('kasir.expenses.index', [
            'date'       => $date,
            'expenses'   => $expenses,
            'total'      => $expenses->sum('amount'),
            'categories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    // 2. Simpan pengeluaran baru
    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_date'        => ['required', 'date', 'before_or_equal:today'],
            'amount'              => ['required', 'numeric', 'min:1', 'max:999999999'],
            'description'         => ['required', 'string', 'max:500'],   // Wajib di database
        ], [], ['expense_category_id' => 'kategori', 'expense_date' => 'tanggal', 'amount' => 'nominal', 'description' => 'keterangan']);

        Expense::create($data + ['user_id' => $request->user()->id]);   // Pencatat = staf yang sedang login

        return redirect()->route('kasir.expenses.index', ['tanggal' => $data['expense_date']])
            ->with('status', 'Pengeluaran Rp' . number_format((float) $data['amount'], 0, ',', '.') . ' tercatat.');
    }

    // 3. Hapus pengeluaran (khusus owner, diatur di route)
    public function destroy(Expense $expense)
    {
        $date = $expense->expense_date?->toDateString();
        $expense->delete();

        return redirect()->route('kasir.expenses.index', ['tanggal' => $date])->with('status', 'Catatan pengeluaran dihapus.');
    }

    // 4. Tambah kategori biaya baru (khusus owner, diatur di route)
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
        ], [], ['name' => 'nama kategori']);

        ExpenseCategory::create(['name' => trim($data['name'])]);

        return back()->with('status', "Kategori \"{$data['name']}\" ditambahkan.");
    }
}
