<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'System Control') | MAAT TECHNOLOGIE BD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Manrope', 'sans-serif'], mono: ['DM Mono', 'monospace'] }, colors: { tech: { 300: '#5eead4', 400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488', 800: '#115e59', 950: '#042f2e' }, cyber: { dark: '#07090e', panel: '#101521', border: '#253040' } } } } };</script>
    <style>body { background:#050609; color:#e2e8f0 } .crt-grid { background-image:linear-gradient(rgba(37,48,64,.32) 1px,transparent 1px),linear-gradient(90deg,rgba(37,48,64,.32) 1px,transparent 1px); background-size:32px 32px; mask-image:linear-gradient(to bottom,transparent,black 14%,black) } .panel { background:rgba(16,21,33,.86); border:1px solid rgba(37,48,64,.92); backdrop-filter:blur(14px) } .scanline { background:linear-gradient(to bottom,transparent 50%,rgba(45,212,191,.018) 50%); background-size:100% 3px } .brand-logo { filter:drop-shadow(0 0 6px rgba(45,212,191,.24)) }</style>
</head>
<body class="min-h-screen font-sans antialiased">
    <div class="pointer-events-none fixed inset-0 -z-10 crt-grid opacity-80"></div><div class="pointer-events-none fixed inset-0 z-50 scanline"></div>
    <header class="sticky top-0 z-40 border-b border-cyber-border bg-[#07090e]/95 backdrop-blur-xl"><div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6"><div class="flex items-center gap-3"><a href="{{ route('home') }}" aria-label="MAAT TECHNOLOGIE BD storefront"><img src="{{ asset('images/brand/maat-tech-logo.png') }}" alt="MAAT TECHNOLOGIE BD logo" class="brand-logo h-11 w-16 object-contain"></a><div class="border-l-2 border-tech-500 pl-3"><p class="font-mono text-sm font-bold tracking-wider text-white">{{ auth('admin')->user()?->is_lead ? 'LEAD_ADMIN' : 'SYSTEM_OPERATOR' }}</p><p class="font-mono text-[9px] tracking-[.16em] text-tech-400">{{ auth('admin')->user()?->is_lead ? 'CLEARANCE_LEVEL: ALPHA' : 'CONTROL_NODE: ACTIVE' }}</p></div></div><div class="flex items-center gap-3"><span class="hidden items-center gap-2 border border-emerald-800/60 bg-emerald-950/40 px-3 py-1 font-mono text-[10px] text-emerald-400 sm:flex"><i class="h-1.5 w-1.5 rounded-full bg-emerald-400"></i>SECURE_SOCKET_ACTIVE</span><a href="{{ route('admin.products') }}" class="hidden border border-cyber-border px-3 py-2 text-xs font-mono text-slate-300 hover:border-tech-600 hover:text-tech-300 md:block">CATALOG</a><form method="POST" action="{{ route('admin.logout') }}">@csrf<button title="Log out" class="grid h-9 w-9 place-items-center text-slate-500 hover:text-rose-300"><i data-lucide="power" class="h-4 w-4"></i></button></form></div></div></header>
    @if(session('status'))<div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6"><div class="border border-tech-800 bg-tech-950/60 px-4 py-3 text-sm text-tech-200">{{ session('status') }}</div></div>@endif
    @yield('content')
    <script>lucide.createIcons();</script>
</body>
</html>