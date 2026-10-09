@extends('layouts.admin')
@section('title', 'Sales & Orders')

@section('content')
@php
    $statusColors = ['pending' => 'text-amber-300 border-amber-800', 'processing' => 'text-cyan-300 border-cyan-800', 'shipped' => 'text-violet-300 border-violet-800', 'delivered' => 'text-emerald-300 border-emerald-800', 'cancelled' => 'text-rose-300 border-rose-800', 'refunded' => 'text-orange-300 border-orange-800'];
    $transitions = ['pending' => ['pending','processing','cancelled'], 'processing' => ['processing','shipped','cancelled'], 'shipped' => ['shipped','delivered','refunded'], 'delivered' => ['delivered','refunded'], 'cancelled' => ['cancelled'], 'refunded' => ['refunded']];
@endphp
<main class="mx-auto max-w-[95rem] px-4 py-8 sm:px-6">
    <header class="mb-7"><p class="font-mono text-xs tracking-[.2em] text-tech-400">COMMERCIAL_LEDGER</p><h1 class="mt-2 text-3xl font-bold text-white">Sales & orders</h1><p class="mt-2 text-sm text-slate-400">Search every order, review line items, monitor revenue, and progress fulfillment.</p></header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">FILTERED_ORDERS</p><strong class="mt-2 block text-2xl text-white">{{ number_format($filteredOrderCount) }}</strong></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">CONFIRMED_SALES</p><strong class="mt-2 block text-2xl text-emerald-300">৳{{ number_format($filteredRevenue, 2) }}</strong></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">AVERAGE_CONFIRMED_ORDER</p><strong class="mt-2 block text-2xl text-cyan-300">৳{{ number_format($filteredAverage, 2) }}</strong></div>
    </section>

    <section class="panel mt-5 rounded-lg p-4"><div class="grid grid-cols-3 gap-3 sm:grid-cols-6">@foreach(['pending','processing','shipped','delivered','cancelled','refunded'] as $status)<a href="{{ route('admin.sales', ['status' => $status]) }}" class="rounded border border-cyber-border bg-[#080c12] p-3 text-center"><span class="block font-mono text-[9px] text-slate-500">{{ strtoupper($status) }}</span><strong class="mt-1 block text-lg {{ explode(' ', $statusColors[$status])[0] }}">{{ $statusCounts->get($status, 0) }}</strong></a>@endforeach</div></section>

    <form method="GET" action="{{ route('admin.sales') }}" class="panel mt-5 grid gap-3 rounded-lg p-4 md:grid-cols-[1.5fr,1fr,1fr,1fr,auto,auto]">
        <label class="font-mono text-[10px] text-slate-400">SEARCH<input name="q" value="{{ request('q') }}" placeholder="Order, customer, phone, tracking" class="mt-2 w-full rounded border border-cyber-border bg-[#080c12] px-3 py-2.5 text-sm text-white"></label>
        <label class="font-mono text-[10px] text-slate-400">STATUS<select name="status" class="mt-2 w-full rounded border border-cyber-border bg-[#080c12] px-3 py-2.5 text-sm text-white"><option value="">All statuses</option>@foreach(['pending','processing','shipped','delivered','cancelled','refunded'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label class="font-mono text-[10px] text-slate-400">FROM<input type="date" name="from" value="{{ request('from') }}" class="mt-2 w-full rounded border border-cyber-border bg-[#080c12] px-3 py-2 text-sm text-white"></label>
        <label class="font-mono text-[10px] text-slate-400">TO<input type="date" name="to" value="{{ request('to') }}" class="mt-2 w-full rounded border border-cyber-border bg-[#080c12] px-3 py-2 text-sm text-white"></label>
        <button class="self-end rounded border border-tech-500 bg-tech-700 px-4 py-2.5 text-xs font-mono text-white">FILTER</button><a href="{{ route('admin.sales') }}" class="self-end rounded border border-cyber-border px-4 py-2.5 text-center text-xs font-mono text-slate-300">RESET</a>
    </form>

    @if(!$canWrite)<div class="mt-5 border border-amber-800 bg-amber-950/40 px-4 py-3 text-sm text-amber-200">Order data is fully visible. Enable 2FA from Overview to unlock status and tracking updates.</div>@endif

    <section class="panel mt-5 overflow-hidden rounded-lg">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="border-b border-cyber-border bg-slate-950/60 text-[10px] font-mono text-slate-500"><tr><th class="px-4 py-3">ORDER</th><th class="px-4 py-3">CUSTOMER / DELIVERY</th><th class="px-4 py-3">LINE ITEMS</th><th class="px-4 py-3">PAYMENT</th><th class="px-4 py-3">AMOUNT</th><th class="px-4 py-3">FULFILLMENT</th></tr></thead>
            <tbody class="divide-y divide-cyber-border">@forelse($orders as $order)
                <tr class="align-top hover:bg-white/[.02]">
                    <td class="whitespace-nowrap px-4 py-4"><p class="font-mono text-xs text-tech-300">{{ $order->order_number }}</p><p class="mt-1 text-xs text-slate-500">{{ ($order->placed_at ?: $order->created_at)->format('d M Y, H:i') }}</p>@if($order->user)<span class="mt-2 inline-block border border-cyber-border px-2 py-1 text-[9px] font-mono text-slate-400">ACCOUNT #{{ $order->user_id }}</span>@else<span class="mt-2 inline-block border border-cyber-border px-2 py-1 text-[9px] font-mono text-slate-400">GUEST</span>@endif</td>
                    <td class="min-w-60 px-4 py-4"><strong class="text-white">{{ $order->customer_name ?: $order->user?->name ?: 'Guest customer' }}</strong><p class="mt-1 text-xs text-slate-400">{{ $order->customer_phone ?: $order->user?->phone ?: 'No phone' }}</p><p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">{{ $order->address ?: 'No address' }}@if($order->district) · {{ $order->district }}@endif</p></td>
                    <td class="min-w-72 px-4 py-4"><div class="space-y-2">@foreach($order->items as $item)<div class="flex justify-between gap-4 text-xs"><span class="text-slate-300">{{ $item->product_name }}@if($item->variant_label) · {{ $item->variant_label }}@endif <span class="text-slate-600">× {{ $item->quantity }}</span></span><span class="whitespace-nowrap text-slate-500">৳{{ number_format((float) $item->unit_price * $item->quantity, 2) }}</span></div>@endforeach</div></td>
                    <td class="px-4 py-4 text-xs"><span class="text-slate-300">{{ strtoupper($order->payment_method) }}</span><span class="mt-1 block text-slate-500">{{ strtoupper($order->payment_status) }}</span><span class="mt-1 block text-slate-600">{{ strtoupper($order->shipping_method) }}</span></td>
                    <td class="whitespace-nowrap px-4 py-4"><strong class="text-white">৳{{ number_format((float) $order->total, 2) }}</strong><span class="mt-1 block text-xs text-slate-500">Subtotal ৳{{ number_format((float) $order->subtotal, 2) }}</span><span class="block text-xs text-slate-600">Delivery ৳{{ number_format((float) $order->shipping_fee, 2) }}</span></td>
                    <td class="min-w-72 px-4 py-4"><span class="mb-2 inline-block rounded border px-2 py-1 text-[9px] font-mono {{ $statusColors[$order->status] ?? 'border-cyber-border text-white' }}">{{ strtoupper($order->status) }}</span><form method="POST" action="{{ route('admin.orders.update', $order) }}" class="grid gap-2">@csrf @method('PATCH')<select name="status" @disabled(!$canWrite) class="rounded border border-cyber-border bg-[#080c12] px-3 py-2 text-xs text-white disabled:opacity-50">@foreach($transitions[$order->status] ?? [$order->status] as $status)<option value="{{ $status }}" @selected($order->status === $status)>{{ strtoupper($status) }}</option>@endforeach</select><input name="tracking_number" value="{{ $order->tracking_number }}" @disabled(!$canWrite) placeholder="Tracking number" class="rounded border border-cyber-border bg-[#080c12] px-3 py-2 text-xs text-white disabled:opacity-50"><button @disabled(!$canWrite) class="rounded border border-tech-600 px-3 py-2 text-[10px] font-mono text-tech-300 disabled:opacity-40">SAVE_FULFILLMENT</button></form></td>
                </tr>
            @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No orders match these filters.</td></tr>@endforelse</tbody>
        </table></div>
        @if($orders->hasPages())<div class="border-t border-cyber-border px-5 py-4">{{ $orders->links() }}</div>@endif
    </section>
</main>
@endsection
