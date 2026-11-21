{{-- Label status cucian berwarna. Dipakai: include('kasir.orders._status-badge', ['order' => $order]) --}}
@php
    $colors = ['received' => '#9aa5b1', 'washing' => '#6b8fd6', 'drying' => '#8f7ad6', 'ironing' => '#e0a14a',
               'ready_for_pickup' => '#47a990', 'completed' => '#4b3f48'];
@endphp
<span class="badge rounded-pill" style="background:{{ $colors[$order->order_status] ?? '#999' }}">{{ $order->statusLabel() }}</span>
