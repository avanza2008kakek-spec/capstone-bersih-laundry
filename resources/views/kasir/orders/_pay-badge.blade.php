{{-- Label status bayar berwarna. Dipakai: include('kasir.orders._pay-badge', ['order' => $order]) --}}
@php $colors = ['unpaid' => '#e05a6b', 'partial' => '#e0a14a', 'paid' => '#47a990']; @endphp
<span class="badge rounded-pill" style="background:{{ $colors[$order->payment_status] ?? '#999' }}">{{ $order->paymentLabel() }}</span>
