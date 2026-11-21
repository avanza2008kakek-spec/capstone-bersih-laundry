<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Customer;                                        // Pelanggan
use App\Models\Order;                                           // Nota transaksi
use App\Models\OrderStatusLog;                                  // Riwayat perubahan status cucian
use App\Models\Payment;                                         // Setoran pembayaran (DP / lunas / pelunasan)
use App\Models\Service;                                         // Paket layanan & harga
use App\Support\Phone;                                          // Helper format nomor HP
use App\Support\Settings;                                       // Nama laundry (untuk nota & pesan WA)
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Support\Facades\DB;                              // Transaksi database
use Illuminate\Validation\Rule;                                 // Aturan validasi

/**
 * Menu Transaksi (owner & kasir): nota baru, daftar nota, detail, pelunasan, tandai sudah diambil, cetak nota.
 */
class OrderController extends Controller
{
    // 1. Daftar nota dengan pencarian & saringan
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        // Hasil pindai barcode nota (atau ketik nomor nota lengkap) → langsung buka notanya
        if ($q !== '' && ($exact = Order::where('invoice_number', strtoupper($q))->first())) {
            return redirect()->route('kasir.orders.show', $exact);
        }

        $orders = Order::with('customer')
            ->when($q !== '', function ($query) use ($q) {
                // Cari no. nota, nama, atau no. HP pelanggan
                $query->where(fn ($w) => $w->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")));
            })
            ->when($request->query('status'), fn ($query, $s) => $query->where('order_status', $s))
            ->when($request->query('bayar'), fn ($query, $s) => $query->where('payment_status', $s))
            ->when($request->query('tanggal'), fn ($query, $d) => $query->whereDate('order_date', $d))
            ->orderByDesc('order_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();                  // withQueryString: saringan tetap terbawa saat pindah halaman

        return view('kasir.orders.index', compact('orders', 'q'));
    }

    // 2. Form nota baru
    public function create(Request $request)
    {
        return view('kasir.orders.create', [
            'services' => Service::with('category')->where('is_active', 1)->orderBy('name')->get(),
            // Dari halaman pelanggan: pelanggan langsung terpilih
            'customer' => $request->filled('pelanggan') ? Customer::find($request->query('pelanggan')) : null,
        ]);
    }

    // 3. Simpan nota baru (+ pelanggan baru, rincian layanan, riwayat status, pembayaran awal)
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'          => ['nullable', 'integer', 'exists:customers,id'],
            'new_name'             => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'new_phone'            => ['required_without:customer_id', 'nullable', 'string', 'max:30'],
            'new_address'          => ['nullable', 'string', 'max:500'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.service_id'   => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', 1)],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'items.*.notes'        => ['nullable', 'string', 'max:255'],
            'discount'             => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'pay_type'             => ['required', 'in:none,dp,full'],   // Bayar nanti / DP / Lunas
            'pay_amount'           => ['nullable', 'required_if:pay_type,dp', 'numeric', 'min:1'],
            'payment_method'       => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
        ], [
            'new_name.required_without'  => 'Pilih pelanggan atau isi nama pelanggan baru.',
            'new_phone.required_without' => 'Isi no. HP pelanggan baru.',
            'items.required'             => 'Tambahkan minimal 1 layanan.',
            'pay_amount.required_if'     => 'Isi jumlah DP.',
        ], ['items.*.quantity' => 'jumlah', 'discount' => 'diskon', 'pay_amount' => 'jumlah bayar']);

        // Hitung rincian & total dari harga di database (bukan dari browser, supaya harga tidak bisa diakali)
        $services = Service::whereIn('id', collect($data['items'])->pluck('service_id'))->get()->keyBy('id');
        $rows = collect($data['items'])->map(function ($item) use ($services) {
            $svc = $services[$item['service_id']];
            $qty = round((float) $item['quantity'], 2);
            return [
                'service_id' => $svc->id,
                'quantity'   => $qty,
                'unit_price' => (float) $svc->price,
                'subtotal'   => round($qty * (float) $svc->price, 2),
                'notes'      => $item['notes'] ?? null,
                'hours'      => (int) $svc->duration_hours,
            ];
        });
        $total    = round($rows->sum('subtotal'), 2);
        $discount = min(round((float) ($data['discount'] ?? 0), 2), $total);   // Diskon tidak boleh melebihi total
        $final    = round($total - $discount, 2);

        // Pembayaran awal
        $paid = match ($data['pay_type']) {
            'full'  => $final,
            'dp'    => min(round((float) $data['pay_amount'], 2), $final),
            default => 0.0,
        };

        $order = DB::transaction(function () use ($data, $rows, $total, $discount, $final, $paid, $request) {
            $customer = $this->resolveCustomer($data);

            $order = Order::create([
                'invoice_number' => Order::nextInvoiceNumber(),
                'customer_id'    => $customer->id,
                'user_id'        => $request->user()->id,
                'order_date'     => now(),
                'estimate_date'  => now()->addHours($rows->max('hours') ?: 48),  // Estimasi = layanan paling lama
                'total_amount'   => $total,
                'discount'       => $discount,
                'final_amount'   => $final,
                'paid_amount'    => $paid,
                'payment_status' => Order::paymentStatusFor($paid, $final),
                'order_status'   => 'received',
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($rows as $row) {
                $order->items()->create(collect($row)->except('hours')->all());
            }

            $this->log($order, 'received', 'Nota dibuat', $request);

            if ($paid > 0) {
                Payment::create([
                    'order_id'       => $order->id,
                    'user_id'        => $request->user()->id,
                    'payment_date'   => now(),
                    'amount'         => $paid,
                    'payment_method' => $data['payment_method'],
                    'payment_type'   => $paid >= $final ? 'full_payment' : 'down_payment',
                ]);
            }

            return $order;
        });

        return redirect()->route('kasir.orders.show', $order)
            ->with('status', "Nota {$order->invoice_number} berhasil dibuat.");
    }

    // 4. Detail nota
    public function show(Order $order, Settings $settings)
    {
        $order->load(['customer', 'user', 'items.service', 'payments.user', 'statusLogs.user']);
        // Fitur kurir aktif jika tabel courier_tasks & courier_photos sudah dibuat di server (database/sql/courier_tasks.sql)
        $courierReady = \Illuminate\Support\Facades\Schema::hasTable('courier_photos');
        if ($courierReady) {
            \App\Models\CourierPhoto::ensureSchema();
            $order->load('courierTasks.courier', 'courierTasks.photo', 'courierTasks.handoverPhoto');
        }

        return view('kasir.orders.show', [
            'order'        => $order,
            'courierReady' => $courierReady,
            'couriers'     => $courierReady ? CourierTaskController::couriers() : collect(),
            'waLink'  => $this->waLink($order, $settings),        // Kirim nota ke WhatsApp pelanggan
        ]);
    }

    // 5. Terima pembayaran / pelunasan
    public function pay(Request $request, Order $order)
    {
        $remaining = $order->remaining();
        if ($remaining <= 0) {
            return back()->withErrors(['amount' => 'Nota ini sudah lunas.']);
        }

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:1', 'max:' . $remaining],
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'notes'          => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => 'Jumlah bayar melebihi sisa tagihan (Rp' . number_format($remaining, 0, ',', '.') . ').'],
           ['amount' => 'jumlah bayar']);

        DB::transaction(function () use ($order, $data, $request) {
            $amount = round((float) $data['amount'], 2);
            Payment::create([
                'order_id'       => $order->id,
                'user_id'        => $request->user()->id,
                'payment_date'   => now(),
                'amount'         => $amount,
                'payment_method' => $data['payment_method'],
                'payment_type'   => 'settlement',               // Pembayaran setelah nota dibuat = pelunasan/cicilan
                'notes'          => $data['notes'] ?? null,
            ]);
            $paid = round((float) $order->paid_amount + $amount, 2);
            $order->update(['paid_amount' => $paid, 'payment_status' => Order::paymentStatusFor($paid, (float) $order->final_amount)]);
        });

        return back()->with('status', 'Pembayaran Rp' . number_format((float) $data['amount'], 0, ',', '.') . ' tercatat.');
    }

    // 6. Tandai sudah diambil pelanggan (hanya jika siap diambil & sudah lunas)
    public function pickup(Request $request, Order $order)
    {
        if ($order->order_status !== 'ready_for_pickup') {
            return back()->withErrors(['pickup' => 'Cucian belum berstatus "Siap diambil".']);
        }
        if ($order->remaining() > 0) {
            return back()->withErrors(['pickup' => 'Lunasi dulu sisa tagihan sebelum cucian diambil.']);
        }

        $order->update(['order_status' => 'completed', 'completion_date' => now()]);
        $this->log($order, 'completed', 'Diambil pelanggan', $request);

        return back()->with('status', "Nota {$order->invoice_number} ditandai sudah diambil.");
    }

    // 7. Cetak nota (format struk 58/80 mm, langsung membuka dialog cetak)
    public function receipt(Order $order)
    {
        $order->load(['customer', 'user', 'items.service']);
        return view('kasir.orders.receipt', compact('order'));
    }

    // ---------- Fungsi bantu ----------

    // Pelanggan terpilih, atau pelanggan baru (kalau no. HP sudah terdaftar, pakai data yang ada)
    private function resolveCustomer(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        $normalized = Phone::normalize($data['new_phone']);
        if ($normalized) {
            $existing = Customer::whereIn('phone', Phone::variants($normalized))->first();
            if ($existing) return $existing;
        }

        return Customer::create([
            'name'    => trim($data['new_name']),
            'phone'   => $normalized ? Phone::toLocal($normalized) : trim($data['new_phone']),
            'address' => $data['new_address'] ?? null,
        ]);
    }

    private function log(Order $order, string $status, ?string $notes, Request $request): void
    {
        OrderStatusLog::create([
            'order_id'   => $order->id,
            'user_id'    => $request->user()->id,
            'status'     => $status,
            'notes'      => $notes,
            'created_at' => now(),
        ]);
    }

    // Link wa.me berisi ringkasan nota + link lacak cucian (dikirim manual oleh kasir)
    private function waLink(Order $order, Settings $settings): ?string
    {
        $phone = Phone::normalize($order->customer?->phone);
        if (! $phone) return null;

        $lines = ["Halo {$order->customer->name}, terima kasih sudah mencuci di {$settings->businessName()}.", '',
                  "No. nota: *{$order->invoice_number}*"];
        foreach ($order->items as $item) {
            $lines[] = '- ' . ($item->service->name ?? 'Layanan') . ' ' . rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ''), '0'), ',')
                     . ' ' . ($item->service->unit ?? '') . ': Rp' . number_format((float) $item->subtotal, 0, ',', '.');
        }
        $lines[] = 'Total: Rp' . number_format((float) $order->final_amount, 0, ',', '.') . ' (' . $order->paymentLabel() . ')';
        if ($order->remaining() > 0) {
            $lines[] = 'Sisa tagihan: Rp' . number_format($order->remaining(), 0, ',', '.');
            $accounts = $settings->bankAccounts();
            if (count($accounts) > 0) {
                $lines[] = '';
                $lines[] = 'Transfer pembayaran:';
                foreach ($accounts as $acc) {
                    $lines[] = "- {$acc['bank']} {$acc['number']} a.n. {$acc['holder']}";
                }
            }
            if ($settings->qrisImage()) {
                $lines[] = 'QRIS tersedia di link Lacak Nota di bawah.';
            }
        }
        $lines[] = 'Estimasi selesai: ' . $order->estimate_date?->format('d-m-Y H:i');
        $lines[] = '';
        $lines[] = 'Lacak cucian: ' . route('customer.home', ['invoice' => $order->invoice_number]) . '#lacak';

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode(implode("\n", $lines));
    }
}
