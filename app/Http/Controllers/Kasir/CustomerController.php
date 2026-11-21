<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Customer;                                        // Pelanggan
use App\Support\Phone;                                          // Helper format nomor HP
use Illuminate\Http\Request;                                    // Penangan request HTTP

/**
 * Menu Pelanggan (owner & kasir): cari, tambah, ubah, lihat riwayat nota & sisa tagihan.
 */
class CustomerController extends Controller
{
    // 1. Daftar pelanggan + pencarian
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->withCount('orders')                               // Jumlah nota per pelanggan
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('kasir.customers.index', compact('customers', 'q'));
    }

    // 2. Cari cepat untuk form Nota baru (jawaban JSON, maks. 8 hasil)
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) return response()->json([]);

        // Cocokkan juga no. HP yang diketik dengan format 08xx / 628xx
        $digits = preg_replace('/\D+/', '', $q);
        $phones = ($digits !== '' && ($n = Phone::normalize($digits))) ? Phone::variants($n) : [];

        return response()->json(
            Customer::where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->when($phones, fn ($query) => $query->orWhereIn('phone', $phones))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'phone'])
        );
    }

    // 3. Form tambah pelanggan
    public function create()
    {
        return view('kasir.customers.form', ['customer' => new Customer()]);
    }

    // 4. Simpan pelanggan baru (no. HP tidak boleh dobel walau beda format)
    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($dup = $this->duplicate($data['phone'])) {
            return back()->withInput()->withErrors(['phone' => "No. HP ini sudah terdaftar atas nama {$dup->name}."]);
        }

        $customer = Customer::create($data);
        return redirect()->route('kasir.customers.show', $customer)->with('status', 'Pelanggan berhasil ditambahkan.');
    }

    // 5. Detail pelanggan: data, riwayat nota, sisa tagihan
    public function show(Customer $customer)
    {
        $orders = $customer->orders()->orderByDesc('order_date')->get();

        return view('kasir.customers.show', [
            'customer'  => $customer,
            'orders'    => $orders,
            'unpaid'    => $orders->sum(fn ($o) => $o->remaining()),  // Total sisa tagihan semua nota
        ]);
    }

    // 6. Form ubah pelanggan
    public function edit(Customer $customer)
    {
        return view('kasir.customers.form', compact('customer'));
    }

    // 7. Simpan perubahan
    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request);

        if (($dup = $this->duplicate($data['phone'])) && ! $dup->is($customer)) {
            return back()->withInput()->withErrors(['phone' => "No. HP ini sudah terdaftar atas nama {$dup->name}."]);
        }

        // No. HP diganti → status "terverifikasi WA" dicabut, karena nomor baru belum pernah diverifikasi
        if ($data['phone'] !== $customer->phone) {
            $data['phone_verified_at'] = null;
        }

        $customer->update($data);
        return redirect()->route('kasir.customers.show', $customer)->with('status', 'Data pelanggan berhasil disimpan.');
    }

    // ---------- Fungsi bantu ----------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => 'nama', 'phone' => 'no. HP', 'address' => 'alamat']);

        $normalized = Phone::normalize($data['phone']);
        if (! $normalized) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'No. HP tidak valid. Contoh: 081234567890']);
        }
        $data['phone'] = Phone::toLocal($normalized);           // Disimpan seragam format 08xx
        $data['name'] = trim($data['name']);
        return $data;
    }

    private function duplicate(string $phone): ?Customer
    {
        return Customer::whereIn('phone', Phone::variants(Phone::normalize($phone)))->first();
    }
}
