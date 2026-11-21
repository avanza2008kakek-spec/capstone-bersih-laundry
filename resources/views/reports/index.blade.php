@extends('layouts.kasir')

@section('title', 'Laporan Keuangan & Operasional')

@section('content')
<style>
    /* Styling khusus kartu laporan & metrik keuangan */
    .rep-card {
        background: #fff;
        border: 2px solid var(--mint-line);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 20px;
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .rep-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(71, 169, 144, .16);
    }
    .metric-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .metric-title {
        font-size: .84rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6c757d;
        margin-bottom: 4px;
    }
    .metric-num {
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.2;
    }
    .preset-pill {
        font-size: .8rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 999px;
        border: 1px solid var(--mint-line);
        background: #fff;
        color: var(--ink);
        text-decoration: none;
        cursor: pointer;
        transition: all .15s;
    }
    .preset-pill:hover, .preset-pill.active {
        background: var(--teal);
        color: #fff;
        border-color: var(--teal);
    }

    /* Print stylesheet untuk cetak fisik atau Simpan ke PDF */
    @media print {
        header.kasir-top, aside.col-md-3, .no-print, .set-nav {
            display: none !important;
        }
        main.col-md-9 {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
            padding: 0 !important;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            font-size: 12pt;
        }
        .k-card, .rep-card {
            border: 1px solid #ccc !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        .print-header {
            display: block !important;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
    }
    @media screen {
        .print-header {
            display: none;
        }
    }
</style>

<!-- KOP KHUSUS SAAT DICETAK KE PRINTER / PDF -->
<div class="print-header text-center">
    <h2 class="mb-1 fw-bold">{{ $businessName }}</h2>
    <h4 class="mb-1">LAPORAN KEUANGAN &amp; OPERASIONAL</h4>
    <p class="mb-0 text-muted small">
        Periode: {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s.d. {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}
        · Dicetak pada: {{ now()->isoFormat('D MMMM Y, HH:mm') }} WIB
    </p>
</div>

<!-- ================= 1. KEPALA HALAMAN & FILTER PERIODE ================= -->
<div class="k-card mb-4 no-print">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="mb-1"><i class="bi bi-graph-up-arrow"></i> Laporan Keuangan &amp; Operasional</h2>
            <p class="opacity-75 mb-0">
                Ringkasan arus kas, laba rugi operasional, beban biaya, dan piutang usaha periode
                <b>{{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }}</b> s.d. <b>{{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</b>.
            </p>
        </div>
        <button type="button" onclick="window.print()" class="btn-pill btn-white py-2 px-3 small">
            <i class="bi bi-printer-fill"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Shortcut Tombol Periode Cepat -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pt-2 border-top">
        <span class="small fw-bold opacity-75"><i class="bi bi-calendar3"></i> Periode Cepat:</span>
        <button type="button" class="preset-pill" onclick="setPeriod('today')">Hari Ini</button>
        <button type="button" class="preset-pill" onclick="setPeriod('this_week')">7 Hari Terakhir</button>
        <button type="button" class="preset-pill" onclick="setPeriod('this_month')">Bulan Ini</button>
        <button type="button" class="preset-pill" onclick="setPeriod('last_month')">Bulan Lalu</button>
        <button type="button" class="preset-pill" onclick="setPeriod('this_year')">Tahun Ini</button>
    </div>

    <!-- Form Filter Tanggal -->
    <form method="GET" action="{{ route('reports.index') }}" id="filter-form" class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-4">
            <label class="form-label small fw-bold mb-1" for="start_date">Tanggal Mulai:</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $startDate }}" required>
        </div>
        <div class="col-sm-6 col-md-4">
            <label class="form-label small fw-bold mb-1" for="end_date">Tanggal Selesai:</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $endDate }}" required>
        </div>
        <div class="col-12 col-md-4">
            <button type="submit" class="btn-pill btn-yellow w-100 py-2">
                <i class="bi bi-funnel-fill"></i> Tampilkan Laporan
            </button>
        </div>
    </form>
</div>

<!-- ================= 2. KARTU METRIK FINANSIAL UTAMA ================= -->
<div class="row g-3 mb-4">
    <!-- 1. Total Kas Masuk -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Kas Masuk (Cash In)</span>
                <div class="metric-icon-wrap" style="background: rgba(71, 169, 144, .14); color: var(--teal);">
                    <i class="bi bi-arrow-down-left-circle-fill"></i>
                </div>
            </div>
            <div class="metric-num text-success">Rp {{ number_format($cashIn, 0, ',', '.') }}</div>
            <small class="opacity-75 d-block mt-1">Uang riil diterima kasir / rekening</small>
        </div>
    </div>

    <!-- 2. Total Kas Keluar -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Kas Keluar (Cash Out)</span>
                <div class="metric-icon-wrap" style="background: rgba(224, 90, 107, .14); color: #e05a6b;">
                    <i class="bi bi-arrow-up-right-circle-fill"></i>
                </div>
            </div>
            <div class="metric-num text-danger">Rp {{ number_format($cashOut, 0, ',', '.') }}</div>
            <small class="opacity-75 d-block mt-1">Total pengeluaran operasional usaha</small>
        </div>
    </div>

    <!-- 3. Arus Kas Bersih -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card" style="border-color: {{ $netCashFlow >= 0 ? 'var(--mint-line)' : '#f8d7da' }};">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Arus Kas Bersih (Net Cash)</span>
                <div class="metric-icon-wrap" style="background: {{ $netCashFlow >= 0 ? 'rgba(71, 169, 144, .14)' : 'rgba(224, 90, 107, .14)' }}; color: {{ $netCashFlow >= 0 ? 'var(--teal)' : '#e05a6b' }};">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="metric-num {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                Rp {{ number_format($netCashFlow, 0, ',', '.') }}
            </div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <small class="opacity-75">Selisih kas masuk &amp; keluar</small>
                <span class="badge rounded-pill {{ $netCashFlow >= 0 ? 'bg-success' : 'bg-danger' }} text-white" style="font-size: .68rem;">
                    {{ $netCashFlow >= 0 ? 'SURPLUS KAS' : 'DEFISIT KAS' }}
                </span>
            </div>
        </div>
    </div>

    <!-- 4. Pendapatan Pesanan Selesai -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Omzet Pesanan Selesai</span>
                <div class="metric-icon-wrap" style="background: rgba(13, 110, 253, .12); color: #0d6efd;">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
            </div>
            <div class="metric-num text-primary">Rp {{ number_format($revenue, 0, ',', '.') }}</div>
            <small class="opacity-75 d-block mt-1">Dari {{ $completedOrdersCount }} cucian selesai ({{ $totalOrdersInPeriod }} total nota)</small>
        </div>
    </div>

    <!-- 5. Laba Bersih Operasional -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Laba Bersih Operasional</span>
                <div class="metric-icon-wrap" style="background: {{ $netProfit >= 0 ? 'rgba(71, 169, 144, .14)' : 'rgba(224, 90, 107, .14)' }}; color: {{ $netProfit >= 0 ? 'var(--teal)' : '#e05a6b' }};">
                    <i class="bi bi-trophy-fill"></i>
                </div>
            </div>
            <div class="metric-num {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <small class="opacity-75">Omzet selesai - Total beban</small>
                <span class="badge rounded-pill {{ $netProfit >= 0 ? 'bg-success' : 'bg-danger' }} text-white" style="font-size: .68rem;">
                    {{ $netProfit >= 0 ? 'LABA UNTUNG' : 'RUGI' }}
                </span>
            </div>
        </div>
    </div>

    <!-- 6. Total Piutang Belum Lunas -->
    <div class="col-sm-6 col-xl-4">
        <div class="rep-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="metric-title">Piutang Belum Lunas</span>
                <div class="metric-icon-wrap" style="background: rgba(255, 193, 7, .18); color: #b78103;">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
            <div class="metric-num" style="color: #b78103;">Rp {{ number_format($totalReceivables, 0, ',', '.') }}</div>
            <small class="opacity-75 d-block mt-1">Dari {{ $receivables->count() }} nota belum lunas / DP</small>
        </div>
    </div>
</div>

<!-- ================= 3. RINCIAN TABEL TRANSAKSI & BIAYA ================= -->
<div class="row g-4">
    <!-- Kolom Kiri: Beban Operasional & Metode Pembayaran -->
    <div class="col-lg-5">
        <!-- Rincian Beban Operasional per Kategori -->
        <div class="k-card mb-4">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Beban Operasional</h5>
                <span class="badge rounded-pill text-white" style="background: var(--purple);">
                    Total: Rp {{ number_format($totalExpense, 0, ',', '.') }}
                </span>
            </div>
            <p class="small opacity-75 mb-3">Pengeluaran kas dikelompokkan menurut kategori biaya operasional.</p>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="font-size:.82rem;">Kategori Biaya</th>
                            <th scope="col" class="text-center" style="font-size:.82rem; width: 60px;">Porsi</th>
                            <th scope="col" class="text-end" style="font-size:.82rem;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expensesByCategory as $catName => $amount)
                            @php
                                $pct = $totalExpense > 0 ? round(($amount / $totalExpense) * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $catName }}</td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-light text-dark border" style="font-size: .72rem;">{{ $pct }}%</span>
                                </td>
                                <td class="text-end text-danger fw-bold">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3 small">
                                    <i class="bi bi-inbox d-block fs-4 mb-1"></i>
                                    Tidak ada pengeluaran pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($expensesByCategory->isNotEmpty())
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTAL BEBAN</td>
                                <td class="text-center">100%</td>
                                <td class="text-end text-danger fs-6">Rp {{ number_format($totalExpense, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <!-- Rincian Metode Pembayaran Kas Masuk -->
        <div class="k-card">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom">
                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Arus Kas per Metode</h5>
                <span class="badge rounded-pill text-white" style="background: var(--teal);">
                    {{ $paymentsByMethod->sum('count') }} Transaksi
                </span>
            </div>
            <p class="small opacity-75 mb-3">Distribusi uang kas masuk berdasarkan cara bayar pelanggan.</p>

            <div class="d-flex flex-column gap-2">
                @php
                    $methods = [
                        'cash'     => ['name' => 'Tunai / Cash', 'icon' => 'bi-cash', 'color' => 'success'],
                        'qris'     => ['name' => 'QRIS', 'icon' => 'bi-qr-code', 'color' => 'primary'],
                        'transfer' => ['name' => 'Transfer Bank', 'icon' => 'bi-bank', 'color' => 'info'],
                    ];
                @endphp
                @forelse($methods as $mKey => $mInfo)
                    @php
                        $data = $paymentsByMethod->get($mKey);
                        $tot  = $data ? (float) $data->total : 0;
                        $cnt  = $data ? (int) $data->count : 0;
                        $pct  = $cashIn > 0 ? round(($tot / $cashIn) * 100, 1) : 0;
                    @endphp
                    <div class="p-2 px-3 rounded-3 border d-flex justify-content-between align-items-center" style="background: #fafcfb;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi {{ $mInfo['icon'] }} text-{{ $mInfo['color'] }} fs-5"></i>
                            <div>
                                <div class="fw-bold small">{{ $mInfo['name'] }}</div>
                                <small class="text-muted" style="font-size: .72rem;">{{ $cnt }} pembayaran ({{ $pct }}%)</small>
                            </div>
                        </div>
                        <div class="text-end fw-bold text-success">
                            Rp {{ number_format($tot, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-2 small">Belum ada pembayaran.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Rekapitulasi Piutang Pelanggan -->
    <div class="col-lg-7">
        <div class="k-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 pb-2 border-bottom">
                <div>
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Piutang Pelanggan</h5>
                    <small class="opacity-75">Daftar pesanan yang belum dibayar lunas (Belum Bayar &amp; DP)</small>
                </div>
                <span class="badge rounded-pill bg-warning text-dark px-3 py-2 fw-bold fs-6">
                    Rp {{ number_format($totalReceivables, 0, ',', '.') }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="font-size:.82rem;">Invoice</th>
                            <th scope="col" style="font-size:.82rem;">Pelanggan</th>
                            <th scope="col" style="font-size:.82rem;" class="text-center">Status</th>
                            <th scope="col" style="font-size:.82rem;" class="text-end">Tagihan</th>
                            <th scope="col" style="font-size:.82rem;" class="text-end">Sisa Piutang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receivables as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('kasir.orders.show', $order) }}" class="fw-bold text-decoration-none text-teal" target="_blank" title="Buka Detail Transaksi">
                                        {{ $order->invoice_number }} <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                    <small class="d-block text-muted" style="font-size: .72rem;">{{ $order->order_date?->format('d/m/Y') }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 140px;">{{ $order->customer->name ?? '-' }}</div>
                                    <small class="text-muted d-block" style="font-size: .72rem;">{{ $order->customer->phone ?? '' }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill {{ $order->payment_status === 'partial' ? 'bg-warning text-dark' : 'bg-danger text-white' }}" style="font-size: .72rem;">
                                        {{ $order->payment_status === 'partial' ? 'DP' : 'BELUM BAYAR' }}
                                    </span>
                                </td>
                                <td class="text-end small opacity-75">
                                    Rp {{ number_format($order->final_amount, 0, ',', '.') }}
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    Rp {{ number_format($order->remaining, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle-fill text-success d-block fs-3 mb-1"></i>
                                    <span class="fw-bold">Luar biasa! Tidak ada piutang yang tertunggak.</span>
                                    <small class="d-block opacity-75">Semua cucian pelanggan telah terbayar lunas.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($receivables->isNotEmpty())
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">TOTAL PIUTANG USAHA:</td>
                                <td class="text-end text-danger fs-6">Rp {{ number_format($totalReceivables, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Fungsi shortcut tombol periode cepat
    function setPeriod(type) {
        const now = new Date();
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');

        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (type === 'today') {
            const todayStr = formatDate(now);
            startInput.value = todayStr;
            endInput.value = todayStr;
        } else if (type === 'this_week') {
            const past = new Date();
            past.setDate(past.getDate() - 6);
            startInput.value = formatDate(past);
            endInput.value = formatDate(now);
        } else if (type === 'this_month') {
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        } else if (type === 'last_month') {
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        } else if (type === 'this_year') {
            const firstDay = new Date(now.getFullYear(), 0, 1);
            const lastDay = new Date(now.getFullYear(), 11, 31);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        }

        document.getElementById('filter-form').submit();
    }
</script>
@endsection
