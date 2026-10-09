@extends('layouts.admin')
@section('title', 'Admin Overview')

@section('content')
@php
    $canWrite = (bool) $admin->two_factor_enabled && !session('admin_two_factor_enrollment_pending');
    $statusColors = ['pending' => 'text-amber-300', 'processing' => 'text-cyan-300', 'shipped' => 'text-violet-300', 'delivered' => 'text-emerald-300', 'cancelled' => 'text-rose-300', 'refunded' => 'text-orange-300'];
    $transitions = [
        'pending' => ['pending', 'processing', 'cancelled'],
        'processing' => ['processing', 'shipped', 'cancelled'],
        'shipped' => ['shipped', 'delivered', 'refunded'],
        'delivered' => ['delivered', 'refunded'],
        'cancelled' => ['cancelled'],
        'refunded' => ['refunded'],
    ];
@endphp
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-tech-400">{{ $admin->admin_id }} / {{ $admin->is_lead ? 'LEAD ADMIN' : 'OPERATOR' }}</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Business overview</h1>
            <p class="mt-2 text-sm text-slate-400">Sales, orders, customers, inventory, storefront content, and access controls in one place.</p>
        </div>
        <p class="rounded border px-3 py-2 text-xs font-mono {{ $canWrite ? 'border-emerald-800 bg-emerald-950/40 text-emerald-300' : 'border-amber-800 bg-amber-950/40 text-amber-300' }}">{{ $canWrite ? 'EDITING_ENABLED' : 'VIEW_ONLY_UNTIL_2FA' }}</p>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Management sections">
        <a href="{{ route('admin.sales') }}" class="panel rounded-lg p-5 transition hover:border-tech-600"><i data-lucide="chart-no-axes-combined" class="h-6 w-6 text-emerald-300"></i><strong class="mt-4 block text-white">Sales & orders</strong><span class="mt-1 block text-xs text-slate-500">Full order ledger, filters, totals and status updates</span></a>
        <a href="{{ route('admin.products') }}" class="panel rounded-lg p-5 transition hover:border-tech-600"><i data-lucide="package-search" class="h-6 w-6 text-tech-300"></i><strong class="mt-4 block text-white">Products</strong><span class="mt-1 block text-xs text-slate-500">Catalog, categories, prices, media and inventory</span></a>
        <a href="{{ route('admin.customers') }}" class="panel rounded-lg p-5 transition hover:border-tech-600"><i data-lucide="users" class="h-6 w-6 text-cyan-300"></i><strong class="mt-4 block text-white">Customers</strong><span class="mt-1 block text-xs text-slate-500">Accounts, contact information and purchase totals</span></a>
        <a href="{{ route('admin.storefront') }}" class="panel rounded-lg p-5 transition hover:border-tech-600"><i data-lucide="panels-top-left" class="h-6 w-6 text-violet-300"></i><strong class="mt-4 block text-white">Storefront CMS</strong><span class="mt-1 block text-xs text-slate-500">Homepage, pages, navigation, slides and branding</span></a>
        <a href="#security" class="panel rounded-lg p-5 transition hover:border-tech-600"><i data-lucide="shield-check" class="h-6 w-6 text-amber-300"></i><strong class="mt-4 block text-white">Security & team</strong><span class="mt-1 block text-xs text-slate-500">2FA, administrator roster and invitations</span></a>
    </section>

    <section class="mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="panel rounded-lg border-l-2 border-emerald-500 p-5"><p class="font-mono text-[10px] text-slate-500">CONFIRMED_SALES</p><strong class="mt-2 block text-2xl text-emerald-300">৳{{ number_format($confirmedRevenue, 2) }}</strong><span class="mt-1 block text-xs text-slate-500">Processing, shipped and delivered</span></div>
        <div class="panel rounded-lg border-l-2 border-tech-500 p-5"><p class="font-mono text-[10px] text-slate-500">THIS_MONTH</p><strong class="mt-2 block text-2xl text-white">৳{{ number_format($monthRevenue, 2) }}</strong><span class="mt-1 block text-xs text-slate-500">Today ৳{{ number_format($todayRevenue, 2) }}</span></div>
        <div class="panel rounded-lg border-l-2 border-cyan-500 p-5"><p class="font-mono text-[10px] text-slate-500">TOTAL_ORDERS</p><strong class="mt-2 block text-2xl text-cyan-300">{{ number_format($orderCount) }}</strong><span class="mt-1 block text-xs text-slate-500">Average ৳{{ number_format($averageOrderValue, 2) }}</span></div>
        <div class="panel rounded-lg border-l-2 border-amber-500 p-5"><p class="font-mono text-[10px] text-slate-500">PENDING_VALUE</p><strong class="mt-2 block text-2xl text-amber-300">৳{{ number_format($pendingValue, 2) }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $pendingOrders }} order(s) waiting</span></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">REGISTERED_CUSTOMERS</p><strong class="mt-2 block text-2xl text-white">{{ number_format($customerCount) }}</strong></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">ACTIVE_PRODUCTS</p><strong class="mt-2 block text-2xl text-white">{{ $activeProductCount }} <span class="text-sm text-slate-500">/ {{ $productCount }}</span></strong></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">LOW_STOCK</p><strong class="mt-2 block text-2xl {{ $lowStockProducts->isEmpty() ? 'text-emerald-300' : 'text-rose-300' }}">{{ $lowStockProducts->count() }}</strong></div>
        <div class="panel rounded-lg p-5"><p class="font-mono text-[10px] text-slate-500">ACTIVE_ADMINS</p><strong class="mt-2 block text-2xl text-white">{{ $admins->where('status', 'active')->count() }}</strong></div>
    </section>

    <section class="panel mt-7 rounded-lg p-4"><div class="grid grid-cols-3 gap-3 sm:grid-cols-6">@foreach(['pending','processing','shipped','delivered','cancelled','refunded'] as $status)<a href="{{ route('admin.sales', ['status' => $status]) }}" class="rounded border border-cyber-border bg-[#080c12] p-3 text-center hover:border-tech-700"><span class="block font-mono text-[9px] text-slate-500">{{ strtoupper($status) }}</span><strong class="mt-1 block text-xl {{ $statusColors[$status] }}">{{ $statusCounts->get($status, 0) }}</strong></a>@endforeach</div></section>

    <section class="panel mt-7 overflow-hidden rounded-lg">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-cyber-border px-5 py-4"><div><h2 class="font-mono text-sm text-white">RECENT_SALES_AND_ORDERS</h2><p class="mt-1 text-xs text-slate-500">The latest ten orders across registered and guest customers.</p></div><a href="{{ route('admin.sales') }}" class="text-xs font-mono text-tech-300">VIEW_ALL_ORDERS →</a></header>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="border-b border-cyber-border bg-slate-950/50 text-[10px] font-mono text-slate-500"><tr><th class="px-4 py-3">ORDER</th><th class="px-4 py-3">CUSTOMER</th><th class="px-4 py-3">PLACED</th><th class="px-4 py-3">ITEMS</th><th class="px-4 py-3">TOTAL</th><th class="px-4 py-3">STATUS / TRACKING</th></tr></thead>
            <tbody class="divide-y divide-cyber-border">@forelse($recentOrders as $order)
                <tr class="align-top hover:bg-white/[.02]">
                    <td class="px-4 py-4 font-mono text-xs text-tech-300">{{ $order->order_number }}</td>
                    <td class="px-4 py-4"><strong class="block text-white">{{ $order->customer_name ?: $order->user?->name ?: 'Guest customer' }}</strong><span class="text-xs text-slate-500">{{ $order->customer_phone ?: $order->user?->phone ?: 'No phone' }} · {{ $order->district ?: 'No district' }}</span></td>
                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-400">{{ ($order->placed_at ?: $order->created_at)->format('d M Y') }}<span class="block text-slate-600">{{ ($order->placed_at ?: $order->created_at)->format('H:i') }}</span></td>
                    <td class="px-4 py-4 text-xs text-slate-400">{{ $order->items->sum('quantity') }} unit(s)<span class="block text-slate-600">{{ $order->items->pluck('product_name')->take(2)->join(', ') }}</span></td>
                    <td class="whitespace-nowrap px-4 py-4 font-semibold text-white">৳{{ number_format((float) $order->total, 2) }}<span class="block text-[10px] font-normal text-slate-600">{{ strtoupper($order->payment_method) }}</span></td>
                    <td class="min-w-72 px-4 py-4"><form method="POST" action="{{ route('admin.orders.update', $order) }}" class="grid grid-cols-[1fr,1fr,auto] gap-2">@csrf @method('PATCH')<select name="status" @disabled(!$canWrite) class="rounded border border-cyber-border bg-[#080c12] px-2 py-2 text-xs {{ $statusColors[$order->status] ?? 'text-white' }} disabled:opacity-50">@foreach($transitions[$order->status] ?? [$order->status] as $status)<option value="{{ $status }}" @selected($order->status === $status)>{{ strtoupper($status) }}</option>@endforeach</select><input name="tracking_number" value="{{ $order->tracking_number }}" @disabled(!$canWrite) placeholder="Tracking" class="min-w-0 rounded border border-cyber-border bg-[#080c12] px-2 py-2 text-xs text-white disabled:opacity-50"><button @disabled(!$canWrite) class="rounded border border-tech-600 px-3 text-[10px] font-mono text-tech-300 disabled:cursor-not-allowed disabled:opacity-40">SAVE</button></form></td>
                </tr>
            @empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No orders have been placed.</td></tr>@endforelse</tbody>
        </table></div>
    </section>

    <section class="mt-7 grid gap-7 lg:grid-cols-[1.2fr_.8fr]">
        <div class="panel overflow-hidden rounded-lg">
            <header class="flex items-center justify-between border-b border-cyber-border px-5 py-4"><h2 class="font-mono text-sm text-white">LOW_STOCK_INVENTORY</h2><a href="{{ route('admin.products') }}" class="text-xs font-mono text-tech-300">MANAGE_PRODUCTS →</a></header>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-cyber-border text-[10px] font-mono text-slate-500"><tr><th class="px-4 py-3">PRODUCT</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3">STATUS</th><th class="px-4 py-3 text-right">STOCK</th></tr></thead><tbody class="divide-y divide-cyber-border">@forelse($lowStockProducts as $product)<tr><td class="px-4 py-3 text-white">{{ $product->name }}</td><td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $product->sku }}</td><td class="px-4 py-3 text-xs text-slate-400">{{ strtoupper($product->status) }}</td><td class="px-4 py-3 text-right font-mono {{ $product->stock === 0 ? 'text-rose-300' : 'text-amber-300' }}">{{ $product->stock }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-8 text-center text-emerald-300">All active products have healthy stock.</td></tr>@endforelse</tbody></table></div>
        </div>

        <div id="security" class="space-y-7 scroll-mt-32">
            <section class="panel rounded-lg p-5"><h2 class="font-mono text-sm text-white">SECURITY_AND_EDITING</h2><p class="mt-3 text-sm text-slate-400">You can view every management page after login. 2FA is required only when saving business data.</p><div class="mt-4 rounded-lg border border-cyber-border bg-[#080c12] p-4"><div class="flex items-center justify-between gap-3"><div><p class="font-semibold text-white">Two-factor authentication</p><p class="text-xs {{ $admin->two_factor_enabled ? 'text-emerald-300' : 'text-amber-300' }}">{{ $admin->two_factor_enabled ? 'Enabled — editing is unlocked' : 'Disabled — pages are view-only' }}</p></div><i data-lucide="{{ $admin->two_factor_enabled ? 'shield-check' : 'shield-alert' }}" class="h-6 w-6 {{ $admin->two_factor_enabled ? 'text-emerald-300' : 'text-amber-300' }}"></i></div><form method="POST" action="{{ route('admin.two-factor.toggle') }}" class="mt-4 grid gap-2">@csrf<input type="hidden" name="enabled" value="{{ $admin->two_factor_enabled ? 0 : 1 }}"><input type="password" name="current_password" required autocomplete="current-password" placeholder="Current admin password" class="rounded border border-cyber-border bg-slate-950 px-3 py-2 text-xs text-white"><button class="rounded border border-tech-500 bg-tech-700 px-4 py-2 text-xs font-mono text-white">{{ $admin->two_factor_enabled ? 'DISABLE_2FA' : 'ENABLE_2FA_TO_EDIT' }}</button></form></div></section>
            <section class="panel rounded-lg p-5"><h2 class="font-mono text-sm text-white">ADMIN_TEAM</h2><div class="mt-4 space-y-3">@foreach($admins as $member)<div class="flex items-center justify-between rounded border border-cyber-border bg-[#080c12] px-3 py-3 text-sm"><div><p class="font-semibold text-white">{{ $member->name }}</p><p class="text-xs text-slate-500">{{ $member->admin_id }} · {{ $member->email }}</p></div><span class="font-mono text-[9px] {{ $member->status === 'active' ? 'text-emerald-300' : 'text-rose-300' }}">{{ strtoupper($member->status) }}</span></div>@endforeach</div></section>
        </div>
    </section>

    @if($admin->is_lead)
        <section class="panel mt-7 overflow-hidden rounded-lg"><header class="flex items-center justify-between border-b border-cyber-border px-5 py-4"><h2 class="font-mono text-sm text-white">ADMIN_INVITATION_QUEUE</h2><span class="font-mono text-xs text-amber-300">{{ $requests->count() }} OPEN</span></header><div class="divide-y divide-cyber-border">@forelse($requests as $requestItem)<article class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr,auto]"><div><p class="font-mono text-[10px] text-tech-400">{{ $requestItem->proposed_admin_id }}</p><p class="mt-1 font-semibold text-white">{{ $requestItem->name }}</p><p class="text-sm text-slate-500">{{ $requestItem->email }} · {{ strtoupper($requestItem->status) }}</p></div><div class="flex gap-2">@if($requestItem->status === \App\Models\AdminInvitationRequest::STATUS_PENDING)<form method="POST" action="{{ route('admin.invitations.approve', $requestItem) }}">@csrf<button @disabled(!$canWrite) class="rounded border border-emerald-700 px-4 py-2 text-xs text-emerald-300 disabled:opacity-40">APPROVE</button></form><form method="POST" action="{{ route('admin.invitations.reject', $requestItem) }}">@csrf<button @disabled(!$canWrite) class="rounded border border-rose-800 px-4 py-2 text-xs text-rose-300 disabled:opacity-40">REJECT</button></form>@else<form method="POST" action="{{ route('admin.invitations.resend', $requestItem) }}">@csrf<button @disabled(!$canWrite) class="rounded border border-tech-700 px-4 py-2 text-xs text-tech-300 disabled:opacity-40">RESEND</button></form><form method="POST" action="{{ route('admin.invitations.revoke', $requestItem) }}">@csrf<button @disabled(!$canWrite) class="rounded border border-rose-800 px-4 py-2 text-xs text-rose-300 disabled:opacity-40">REVOKE</button></form>@endif</div></article>@empty<div class="px-5 py-8 text-sm text-slate-500">No administrator invitations require action.</div>@endforelse</div></section>
    @endif
</main>
@endsection
