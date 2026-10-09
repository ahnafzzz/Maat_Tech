<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'System Control') | MAAT TECHNOLOGIE BD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { background:#050609; color:#e2e8f0 } .crt-grid { background-image:linear-gradient(rgba(37,48,64,.32) 1px,transparent 1px),linear-gradient(90deg,rgba(37,48,64,.32) 1px,transparent 1px); background-size:32px 32px; mask-image:linear-gradient(to bottom,transparent,black 14%,black) } .panel { background:rgba(16,21,33,.86); border:1px solid rgba(37,48,64,.92); backdrop-filter:blur(14px) } .scanline { background:linear-gradient(to bottom,transparent 50%,rgba(45,212,191,.018) 50%); background-size:100% 3px } .brand-logo { filter:drop-shadow(0 0 6px rgba(45,212,191,.24)) }</style>
</head>
<body class="min-h-screen font-sans antialiased">
    <div class="pointer-events-none fixed inset-0 -z-10 crt-grid opacity-80"></div><div class="pointer-events-none fixed inset-0 z-50 scanline"></div>
    <header class="sticky top-0 z-40 border-b border-cyber-border bg-[#07090e]/95 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <div class="flex items-center gap-3"><a href="{{ route('home') }}" aria-label="MAAT TECHNOLOGIE BD storefront"><img src="{{ asset('images/brand/maat-tech-logo.png') }}" alt="MAAT TECHNOLOGIE BD logo" class="brand-logo h-11 w-16 object-contain"></a><div class="border-l-2 border-tech-500 pl-3"><p class="font-mono text-sm font-bold tracking-wider text-white">{{ auth('admin')->user()?->is_lead ? 'LEAD_ADMIN' : 'SYSTEM_OPERATOR' }}</p><p class="font-mono text-[9px] tracking-[.16em] text-tech-400">{{ auth('admin')->user()?->is_lead ? 'CLEARANCE_LEVEL: ALPHA' : 'CONTROL_NODE: ACTIVE' }}</p></div></div>
            <div class="flex items-center gap-3"><span class="hidden items-center gap-2 border border-emerald-800/60 bg-emerald-950/40 px-3 py-1 font-mono text-[10px] text-emerald-400 sm:flex"><i class="h-1.5 w-1.5 rounded-full bg-emerald-400"></i>{{ auth('admin')->user()?->two_factor_enabled ? '2FA_ACTIVE' : 'VIEW_MODE' }}</span><form method="POST" action="{{ route('admin.logout') }}">@csrf<button title="Log out" class="grid h-9 w-9 place-items-center text-slate-500 hover:text-rose-300"><i data-lucide="power" class="h-4 w-4"></i></button></form></div>
        </div>
        <nav class="mx-auto flex max-w-7xl gap-2 overflow-x-auto border-t border-cyber-border/70 px-4 py-2 text-[10px] font-mono sm:px-6">
            <a href="{{ route('admin.dashboard') }}" class="shrink-0 border px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'border-tech-500 bg-tech-950/60 text-tech-200' : 'border-cyber-border text-slate-400 hover:text-tech-300' }}">OVERVIEW</a>
            <a href="{{ route('admin.sales') }}" class="shrink-0 border px-3 py-2 {{ request()->routeIs('admin.sales') ? 'border-tech-500 bg-tech-950/60 text-tech-200' : 'border-cyber-border text-slate-400 hover:text-tech-300' }}">SALES_ORDERS</a>
            <a href="{{ route('admin.products') }}" class="shrink-0 border px-3 py-2 {{ request()->routeIs('admin.products*') ? 'border-tech-500 bg-tech-950/60 text-tech-200' : 'border-cyber-border text-slate-400 hover:text-tech-300' }}">PRODUCTS</a>
            <a href="{{ route('admin.customers') }}" class="shrink-0 border px-3 py-2 {{ request()->routeIs('admin.customers') ? 'border-tech-500 bg-tech-950/60 text-tech-200' : 'border-cyber-border text-slate-400 hover:text-tech-300' }}">CUSTOMERS</a>
            <a href="{{ route('admin.storefront') }}" class="shrink-0 border px-3 py-2 {{ request()->routeIs('admin.storefront*') ? 'border-tech-500 bg-tech-950/60 text-tech-200' : 'border-cyber-border text-slate-400 hover:text-tech-300' }}">STOREFRONT_CMS</a>
            <a href="{{ route('home') }}" target="_blank" rel="noreferrer" class="shrink-0 border border-cyber-border px-3 py-2 text-slate-400 hover:text-tech-300">VIEW_STORE</a>
        </nav>
    </header>
    @if(auth('admin')->check() && !auth('admin')->user()->two_factor_enabled)
        <div class="border-b border-amber-800 bg-amber-950/50 px-4 py-3 text-center text-xs text-amber-200">All management pages are visible. Enable 2FA in Overview → Security before saving changes or updating orders.</div>
    @endif
    @if(session('status'))<div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6"><div class="border border-tech-800 bg-tech-950/60 px-4 py-3 text-sm text-tech-200">{{ session('status') }}</div></div>@endif
    @if($errors->any())<div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6"><div class="border border-rose-800 bg-rose-950/40 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div></div>@endif
    @yield('content')
</body>
</html>
