<?php

namespace App\Http\Controllers;

use App\Models\Service;                                         // Model paket layanan
use App\Models\Order;                                           // Model transaksi pesanan
use App\Models\Customer;                                        // Model pelanggan (untuk status masuk)
use App\Support\Phone;                                          // Helper penyeragam nomor HP
use Illuminate\Http\Request;                                    // Penangan request HTTP

class CustomerController extends Controller
{
    private const STEP_MAP = [
        'received'         => 1,
        'washing'          => 2,
        'drying'           => 3,
        'ironing'          => 4,
        'ready_for_pickup' => 5,
        'completed'        => 6,
    ];

    public function index(Request $request)
    {
        // 1. Ambil semua layanan aktif untuk katalog & opsi kalkulator estimasi
        $allServices = Service::with('category')->where('is_active', 1)->get(); // with('category'): ambil kategori sekaligus (1 query, bukan 1 per layanan)
        $groupedServices = $allServices->groupBy('category.name');

        // 2. Pelanggan yang sedang masuk (lewat Daftar / Masuk dengan kode WA), null jika belum
        $currentCustomer = $request->session()->has('customer_id')
            ? Customer::find($request->session()->get('customer_id'))
            : null;
        $customerIds = $this->customerIdsFor($currentCustomer);

        // 3. Lacak Nota: HANYA pemilik nota (pelanggan yang masuk dengan nomor WA di nota) yang boleh melihat isinya
        $invoice = strtoupper(trim((string) $request->query('invoice')));
        $trackedOrder = null;
        $trackState = null;                                     // null | 'login' (harus masuk) | 'not_found' (bukan milik / tidak ada)

        if ($invoice !== '') {
            if (! $currentCustomer) {
                // Belum masuk: setelah login lewat kode WA, langsung kembali ke nota ini
                $request->session()->put('after_login', route('customer.home', ['invoice' => $invoice]) . '#lacak');
                $trackState = 'login';
            } else {
                $trackedOrder = Order::with(['customer', 'items.service', 'statusLogs'])
                    ->where('invoice_number', $invoice)
                    ->whereIn('customer_id', $customerIds)      // Nota orang lain tidak ditampilkan
                    ->first();
                $trackState = $trackedOrder ? null : 'not_found';
            }
        }

        // 4. Daftar "Cucian saya" untuk pelanggan yang sudah masuk (5 nota terbaru)
        $myOrders = $currentCustomer
            ? Order::whereIn('customer_id', $customerIds)->orderByDesc('order_date')->orderByDesc('id')->limit(5)->get()
            : collect();

        $response = response()->view('customer.index', compact(
            'allServices',
            'groupedServices',
            'trackedOrder',
            'currentCustomer',
            'trackState',
            'invoice',
            'myOrders'
        ));

        // Cegah browser / CDN / LiteSpeed menyimpan cache halaman Lacak Nota agar perubahan status dari operator langsung terlihat
        if ($currentCustomer || $invoice !== '') {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * Endpoint JSON untuk memperbarui status Lacak Nota & tombol "Cucian saya" secara real-time
     * tanpa perlu muat ulang halaman saat operator mengubah status cucian di menu Antrean.
     */
    public function checkStatus(Request $request)
    {
        $currentCustomer = $request->session()->has('customer_id')
            ? Customer::find($request->session()->get('customer_id'))
            : null;

        if (! $currentCustomer) {
            return response()->json(['authenticated' => false]);
        }

        $customerIds = $this->customerIdsFor($currentCustomer);
        $invoice = strtoupper(trim((string) $request->query('invoice')));

        $myOrders = Order::whereIn('customer_id', $customerIds)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'invoice_number', 'order_status', 'payment_status'])
            ->map(fn (Order $o) => [
                'invoice_number' => $o->invoice_number,
                'order_status'   => $o->order_status,
                'status_label'   => $o->statusLabel(),
            ])
            ->values();

        $tracked = null;
        if ($invoice !== '') {
            $order = Order::with('statusLogs')
                ->where('invoice_number', $invoice)
                ->whereIn('customer_id', $customerIds)
                ->first();

            if ($order) {
                $latestLog = $order->statusLogs->sortByDesc('id')->first();
                $isPaid = $order->payment_status === 'paid';
                $payLabel = ['paid' => 'LUNAS', 'partial' => 'DP', 'unpaid' => 'BELUM BAYAR'][$order->payment_status]
                    ?? strtoupper($order->payment_status);

                $tracked = [
                    'invoice_number' => $order->invoice_number,
                    'order_status'   => $order->order_status,
                    'status_label'   => $order->statusLabel(),
                    'step'           => self::STEP_MAP[$order->order_status] ?? 1,
                    'payment_status' => $order->payment_status,
                    'pay_label'      => $payLabel,
                    'is_paid'        => $isPaid,
                    'paid_formatted' => 'Rp' . number_format((float) $order->paid_amount, 0, ',', '.'),
                    'rem_formatted'  => 'Rp' . number_format($order->remaining(), 0, ',', '.'),
                    'remaining'      => $order->remaining(),
                    'updated_at'     => $latestLog && $latestLog->created_at
                        ? \Illuminate\Support\Carbon::parse($latestLog->created_at)->format('d M Y, H:i') . ' WIB'
                        : null,
                ];
            }
        }

        return response()->json([
            'authenticated' => true,
            'my_orders'     => $myOrders,
            'tracked'       => $tracked,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Ambil semua ID pelanggan yang memiliki nomor WA yang sama (baik format 08xx maupun 628xx),
     * supaya jika kasir pernah membuat data pelanggan terpisah dengan nomor yang sama, notanya tetap tampil.
     */
    private function customerIdsFor(?Customer $customer): array
    {
        if (! $customer) {
            return [];
        }
        $normalized = Phone::normalize($customer->phone);
        if (! $normalized) {
            return [$customer->id];
        }
        return Customer::whereIn('phone', Phone::variants($normalized))
            ->pluck('id')
            ->push($customer->id)
            ->unique()
            ->values()
            ->all();
    }
}
