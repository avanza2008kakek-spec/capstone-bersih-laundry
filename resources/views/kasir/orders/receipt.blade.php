<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Nota {{ $order->invoice_number }}</title>
    <style>
        /* Format struk printer kasir (lebar 58 mm; untuk 80 mm cukup ganti width) */
        @@page { size: 58mm auto; margin: 2mm; }
        body { width: 54mm; margin: 0 auto; font-family: "Courier New", monospace; font-size: 11px; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .no-print { text-align: center; margin: 10px 0; }
        /* QR Code ±30 mm & barcode selebar struk */
        .qr { width: 30mm; height: 30mm; margin: 2px auto; }
        .qr svg { width: 100%; height: 100%; }
        .barcode { width: 100%; max-width: 54mm; height: auto; }
        @@media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="c b" style="font-size:13px">{{ $businessName }}</div>
    <hr>
    <div>No : {{ $order->invoice_number }}</div>
    <div>Tgl: {{ $order->order_date?->format('d-m-Y H:i') }}</div>
    <div>Plg: {{ $order->customer->name ?? '-' }}</div>
    <div>HP : {{ $order->customer->phone ?? '-' }}</div>
    <div>Ksr: {{ $order->user->name ?? '-' }}</div>
    <hr>
    <table>
        @foreach($order->items as $item)
            <tr><td colspan="2">{{ $item->service->name ?? '-' }}</td></tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->service->unit ?? '' }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td class="r">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Total</td><td class="r">{{ number_format((float) $order->total_amount, 0, ',', '.') }}</td></tr>
        @if((float) $order->discount > 0)
            <tr><td>Diskon</td><td class="r">-{{ number_format((float) $order->discount, 0, ',', '.') }}</td></tr>
        @endif
        <tr class="b"><td>Tagihan</td><td class="r">{{ number_format((float) $order->final_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Dibayar</td><td class="r">{{ number_format((float) $order->paid_amount, 0, ',', '.') }}</td></tr>
        <tr class="b"><td>Sisa</td><td class="r">{{ number_format($order->remaining(), 0, ',', '.') }}</td></tr>
    </table>
    <hr>
    <div>Status : {{ $order->paymentLabel() }}</div>
    <div>Selesai: {{ $order->estimate_date?->format('d-m-Y H:i') }}</div>
    @if($order->notes) <div>Catatan: {{ $order->notes }}</div> @endif
    <hr>
    @php
        $settings = app(\App\Support\Settings::class);
        $bankAccounts = $order->remaining() > 0 ? $settings->bankAccounts() : [];
        $qrisImage = $order->remaining() > 0 ? $settings->qrisImage() : null;
        // QR berisi link Lacak Nota (dipindai pelanggan), barcode berisi nomor nota (dipindai kasir)
        $lacakUrl = route('customer.home', ['invoice' => $order->invoice_number]) . '#lacak';
    @endphp
    @if($order->remaining() > 0 && (count($bankAccounts) > 0 || $qrisImage))
        @if(count($bankAccounts) > 0)
            <div class="b">Transfer Pembayaran:</div>
            @foreach($bankAccounts as $acc)
                <div style="margin-bottom:2px">
                    <b>{{ $acc['bank'] }} {{ $acc['number'] }}</b><br>
                    a.n. {{ $acc['holder'] }}
                </div>
            @endforeach
        @endif
        @if($qrisImage)
            <div class="c" style="margin-top:4px">
                <div class="b">Pembayaran QRIS</div>
                <img src="{{ $qrisImage }}" alt="QRIS Pembayaran" style="width:38mm;max-width:100%;height:auto;margin:2px auto;display:block">
            </div>
        @endif
        <hr>
    @endif
    <!-- QR Code: pindai dengan kamera HP untuk melihat status cucian -->
    <div class="c">
        <div id="qr" class="qr" data-text="{{ $lacakUrl }}"></div>
        <div>Pindai untuk lacak cucian</div>
    </div>
    <!-- Barcode Code 128: nomor nota, dipindai kasir saat cucian diambil -->
    <div class="c" style="margin-top:4px">
        <svg id="barcode" class="barcode" data-text="{{ $order->invoice_number }}"></svg>
    </div>
    <hr>
    <div class="c">Lacak cucian di website kami<br>dengan nomor nota di atas.<br>Terima kasih!</div>

    <div class="no-print"><button onclick="window.print()">Cetak lagi</button></div>

    <!-- Library pembuat QR Code & barcode (gratis, dijalankan di browser; data nota tidak dikirim ke mana pun) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        // 1. Gambar QR Code (tingkat koreksi M: tetap terbaca walau struk sedikit kusut)
        try {
            const el = document.getElementById('qr');
            const qr = qrcode(0, 'M');                        // 0 = ukuran otomatis sesuai panjang link
            qr.addData(el.dataset.text);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 2, scalable: true });
        } catch (e) { document.getElementById('qr').textContent = ''; }

        // 2. Gambar barcode Code 128 berisi nomor nota (angka di bawahnya ikut tercetak)
        try {
            JsBarcode('#barcode', document.getElementById('barcode').dataset.text, {
                format: 'CODE128', width: 1, height: 38, displayValue: true, fontSize: 11, margin: 0,
            });
        } catch (e) { document.getElementById('barcode').remove(); }

        // 3. Setelah semua gambar siap, langsung buka dialog cetak
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
