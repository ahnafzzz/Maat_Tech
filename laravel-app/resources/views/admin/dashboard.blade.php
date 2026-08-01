@extends('layouts.admin')
@section('title', 'System Control')
@section('content')
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-tech-400">{{ $admin->admin_id }} / {{ $admin->is_lead ? 'CLEARANCE ALPHA' : 'OPERATOR CLEARANCE' }}</p>
            <h1 class="mt-2 text-3xl font-bold text-white">System Control</h1>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.products') }}" class="inline-flex items-center gap-2 rounded-lg border border-tech-600 px-4 py-2 text-xs font-mono text-tech-300 hover:bg-tech-600 hover:text-white"><i data-lucide="package-plus" class="h-4 w-4"></i>CATALOG_CONTROL</a>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg border border-cyber-border px-4 py-2 text-xs font-mono text-slate-300 hover:border-tech-600 hover:text-tech-300"><i data-lucide="store" class="h-4 w-4"></i>VIEW_STOREFRONT</a>
        </div>
    </header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="panel rounded-lg border-l-2 border-tech-500 p-5"><p class="font-mono text-[10px] text-slate-500">ACTIVE_ADMINS</p><strong class="mt-2 block text-2xl text-white">{{ $admins->where('status', 'active')->count() }}</strong></div>
        <div class="panel rounded-lg border-l-2 border-amber-500 p-5"><p class="font-mono text-[10px] text-slate-500">PENDING_ORDERS</p><strong class="mt-2 block text-2xl text-amber-300">{{ $pendingOrders }}</strong></div>
        <div class="panel rounded-lg border-l-2 border-tech-500 p-5"><p class="font-mono text-[10px] text-slate-500">CATALOG_UNITS</p><strong class="mt-2 block text-2xl text-white">{{ $productCount }}</strong></div>
        <div class="panel rounded-lg border-l-2 border-cyan-500 p-5"><p class="font-mono text-[10px] text-slate-500">TOTAL_ORDERS</p><strong class="mt-2 block text-2xl text-cyan-300">{{ $orderCount }}</strong></div>
    </section>

    @if($admin->is_lead)
        <section class="panel mt-7 overflow-hidden rounded-lg">
            <header class="flex items-center justify-between border-b border-cyber-border px-5 py-4">
                <h2 class="flex items-center gap-2 font-mono text-sm text-white"><i data-lucide="inbox" class="h-4 w-4 text-amber-400"></i>PENDING_INVITATION_REQUESTS</h2>
                <span class="rounded-md border border-amber-800 bg-amber-950/50 px-2 py-1 font-mono text-[10px] text-amber-300">{{ $requests->count() }} QUEUED</span>
            </header>
            <div class="divide-y divide-cyber-border">
                @forelse($requests as $requestItem)
                    <article class="grid gap-4 px-5 py-4 lg:grid-cols-[1.2fr,.8fr]">
                        <div>
                            <p class="font-mono text-[10px] text-tech-400">{{ $requestItem->proposed_admin_id }}</p>
                            <p class="mt-1 font-semibold text-white">{{ $requestItem->name }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ $requestItem->email }}</p>
                            <p class="mt-1 text-xs text-slate-500">Requested by {{ $requestItem->requester?->admin_id }} • {{ $requestItem->created_at?->diffForHumans() }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <form method="POST" action="{{ route('admin.invitations.approve', $requestItem) }}">@csrf<button class="w-full rounded-lg border border-emerald-700 bg-emerald-950/40 px-4 py-3 text-xs font-mono text-emerald-300 hover:bg-emerald-900/50">APPROVE</button></form>
                            <form method="POST" action="{{ route('admin.invitations.reject', $requestItem) }}" class="grid gap-2">@csrf<input name="decision_note" placeholder="Reason (optional)" class="rounded-lg border border-cyber-border bg-[#080c12] px-3 py-2 text-xs text-white outline-none focus:border-tech-500"><button class="rounded-lg border border-rose-900 bg-rose-950/40 px-4 py-3 text-xs font-mono text-rose-300 hover:bg-rose-900/50">REJECT</button></form>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-500">No invitation requests are pending.</div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="mt-7 grid gap-7 lg:grid-cols-[1.2fr,.8fr]">
        <div class="panel overflow-hidden rounded-lg">
            <header class="flex items-center justify-between border-b border-cyber-border px-5 py-4">
                <h2 class="font-mono text-sm text-white">RECENT_ORDERS</h2>
                <span class="font-mono text-[10px] text-slate-500">TRACKING / STATUS / COD</span>
            </header>
            <div class="divide-y divide-cyber-border">
                @forelse($recentOrders as $order)
                    <article class="px-5 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-[10px] text-tech-400">{{ $order->order_number }}</p>
                                <p class="mt-1 font-semibold text-white">{{ $order->customer_name ?: 'Guest customer' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ strtoupper($order->payment_method) }} / {{ strtoupper($order->shipping_method) }} / {{ $order->district }}</p>
                            </div>
                            <div class="rounded-md border border-tech-800 bg-tech-950/50 px-2 py-1 font-mono text-[10px] text-tech-300">TOTAL BDT {{ number_format($order->total) }}</div>
                        </div>
                        <div class="mt-4 space-y-1 text-sm text-slate-400">
                            @foreach($order->items as $item)
                                <div class="flex justify-between gap-3"><span>{{ $item->product->name ?? 'Removed product' }} × {{ $item->quantity }}</span><span>BDT {{ number_format($item->unit_price * $item->quantity) }}</span></div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="mt-4 grid gap-3 lg:grid-cols-[1fr,1fr,auto]">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="rounded-lg border border-cyber-border bg-[#080c12] px-3 py-3 text-sm text-white outline-none focus:border-tech-500">
                                @foreach(['pending','processing','shipped','delivered','cancelled','refunded'] as $status)
                                    <option value="{{ $status }}" @selected($order->status === $status)>{{ strtoupper($status) }}</option>
                                @endforeach
                            </select>
                            <input name="tracking_number" value="{{ $order->tracking_number }}" placeholder="Tracking number" class="rounded-lg border border-cyber-border bg-[#080c12] px-3 py-3 text-sm text-white outline-none focus:border-tech-500">
                            <button class="rounded-lg border border-tech-400 bg-tech-600 px-4 py-3 text-xs font-mono text-white hover:bg-tech-500">UPDATE_ORDER</button>
                        </form>
                    </article>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-500">No recent orders available yet.</div>
                @endforelse
            </div>
        </div>

        <div class="space-y-7">
            <section class="panel rounded-lg p-5">
                <h2 class="font-mono text-sm text-white">ACCESS_HARDENING</h2>
                <p class="mt-3 text-sm text-slate-400">Admin email two-factor verification adds a second challenge after password sign-in.</p>
                <div class="mt-4 flex items-center justify-between rounded-lg border border-cyber-border bg-[#080c12] px-4 py-3 text-sm">
                    <div>
                        <p class="font-semibold text-white">Two-factor authentication</p>
                        <p class="text-xs text-slate-500">Current state: {{ $admin->two_factor_enabled ? 'Enabled' : 'Disabled' }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.two-factor.toggle') }}">@csrf<button class="rounded-lg border border-tech-400 bg-tech-600 px-4 py-2 text-xs font-mono text-white hover:bg-tech-500">{{ $admin->two_factor_enabled ? 'DISABLE_2FA' : 'ENABLE_2FA' }}</button></form>
                </div>
            </section>

            <section class="panel rounded-lg p-5">
                <h2 class="font-mono text-sm text-white">LOW_STOCK_WARNING</h2>
                <div class="mt-4 space-y-3">
                    @forelse($recentOrders->pluck('items')->flatten()->pluck('product')->filter()->unique('id')->where('stock', '<=', 5) as $lowStockProduct)
                        <div class="flex items-center justify-between rounded-lg border border-cyber-border bg-[#080c12] px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-white">{{ $lowStockProduct->name }}</p>
                                <p class="text-xs text-slate-500">{{ $lowStockProduct->sku }}</p>
                            </div>
                            <span class="font-mono text-amber-300">{{ $lowStockProduct->stock }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No low-stock alerts from the currently loaded recent order set.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel rounded-lg p-5">
                <h2 class="font-mono text-sm text-white">ADMIN_ROSTER</h2>
                <div class="mt-4 space-y-3">
                    @foreach($admins as $member)
                        <div class="flex items-center justify-between rounded-lg border border-cyber-border bg-[#080c12] px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-white">{{ $member->name }}</p>
                                <p class="text-xs text-slate-500">{{ $member->admin_id }} / {{ $member->email }}</p>
                            </div>
                            <span class="font-mono text-[10px] {{ $member->status === 'active' ? 'text-emerald-300' : 'text-rose-300' }}">{{ strtoupper($member->status) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </section>
</main>
@endsection
