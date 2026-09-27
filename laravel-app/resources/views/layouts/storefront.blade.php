<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MAAT Technologies BD') | MAAT Technologies BD</title>
    <meta name="description" content="@yield('meta_description', 'Precision mechanical arm lighting systems by MAAT Technologies BD')">
    <meta property="og:title" content="@yield('title', 'MAAT Technologies BD') | MAAT Technologies BD">
    <meta property="og:description" content="@yield('meta_description', 'Industrial-grade articulated arm lighting and delivery across Bangladesh.')">
    <meta property="og:image" content="{{ asset('images/brand/maat-tech-logo.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/lucide@latest"></script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'MAAT Technologies BD',
            'url' => url('/'),
            'logo' => asset('images/brand/maat-tech-logo.png'),
            'email' => 'maat.technologies.bd@gmail.com',
            'telephone' => '+8801601934752',
            'sameAs' => [
                'https://www.facebook.com/maattechnologiesbd',
                'https://www.instagram.com/maattechnologiesbd',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @if (env('FACEBOOK_PIXEL_ID'))
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
            document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ env('FACEBOOK_PIXEL_ID') }}');
            fbq('track', 'PageView');
        </script>
    @endif
    @stack('head')
</head>
<body class="min-h-screen font-sans antialiased">
    <nav class="sticky top-0 z-40 border-b border-cyber-border/90 bg-[#090b10]/95 backdrop-blur-xl">
        <div class="tech-rule"></div>
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex min-h-11 shrink-0 items-center gap-3 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-tech-300" aria-label="MAAT Technologies BD home">
                <img src="{{ asset('images/brand/maat-tech-logo.png') }}" alt="MAAT Technologies BD logo" class="brand-logo h-11 w-16 shrink-0 object-contain">
                <span class="hidden leading-none sm:block"><strong class="block text-base font-extrabold tracking-tight text-white">MAAT Technologies BD</strong><small class="font-mono text-[9px] tracking-[.18em] text-tech-400">PRECISION LIGHTING</small></span>
            </a>
            <div class="hidden max-w-md flex-1 md:block">
                <a href="{{ route('products') }}" class="flex min-h-11 items-center justify-center gap-2 rounded-sm border border-cyber-border bg-cyber-panel/60 px-4 py-2 text-sm font-semibold text-slate-300 outline-none transition hover:border-tech-600 hover:text-white focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="lamp-desk" class="h-4 w-4"></i><span>Shop Lamps</span></a>
            </div>
            <div class="flex items-center gap-1 sm:gap-2">
                <a href="{{ route('wishlist.index') }}" aria-label="Your wishlist" title="Your wishlist" class="grid h-11 w-11 place-items-center rounded-sm text-slate-400 outline-none transition hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="heart" class="h-5 w-5"></i></a>
                <a href="{{ route('cart.index') }}" aria-label="Your cart" title="Your cart" class="grid h-11 w-11 place-items-center rounded-sm text-slate-400 outline-none transition hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="shopping-cart" class="h-5 w-5"></i></a>
                <span class="mx-1 hidden h-6 w-px bg-cyber-border sm:block"></span>
                @auth
                    <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center gap-2 rounded-sm border border-cyber-border px-3 py-2 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="user" class="h-4 w-4"></i><span class="hidden sm:inline">Account</span></a>
                @else
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center gap-2 rounded-sm border border-cyber-border px-3 py-2 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="log-in" class="h-4 w-4"></i><span class="hidden sm:inline">Sign in</span></a>
                @endauth
            </div>
        </div>
    </nav>
    @if(session('status'))
        <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6"><div class="border border-tech-700/70 bg-tech-950/60 px-4 py-3 text-sm text-tech-200">{{ session('status') }}</div></div>
    @endif
    @if ($errors->any())
        <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6"><div class="border border-rose-800 bg-rose-950/40 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div></div>
    @endif
    @yield('content')
    <footer class="mt-16 border-t border-cyber-border bg-[#07090d]/90">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-4 py-8 text-[10px] font-mono text-slate-500 sm:flex-row sm:px-6">
            <span>2026 MAAT Technologies BD</span>
            <span class="flex items-center gap-2 text-tech-500"><i class="status-dot h-2 w-2 bg-tech-400"></i>Storefront online</span>
            <a href="{{ route('admin.login') }}" class="inline-flex min-h-11 items-center transition hover:text-tech-300">Admin access</a>
        </div>
        <div class="mx-auto grid max-w-7xl gap-3 border-t border-cyber-border px-4 py-5 text-xs text-slate-400 sm:grid-cols-2 lg:grid-cols-4 sm:px-6">
            <div class="space-y-2">
                <a href="{{ route('about') }}" class="block hover:text-tech-300">About</a>
                <a href="{{ route('contact') }}" class="block hover:text-tech-300">Contact</a>
                <a href="{{ route('faq') }}" class="block hover:text-tech-300">FAQ</a>
            </div>
            <div class="space-y-2">
                <a href="{{ route('shipping') }}" class="block hover:text-tech-300">Shipping Policy</a>
                <a href="{{ route('returns') }}" class="block hover:text-tech-300">Return Policy</a>
                <a href="{{ route('refund') }}" class="block hover:text-tech-300">Refund Policy</a>
            </div>
            <div class="space-y-2">
                <a href="{{ route('privacy') }}" class="block hover:text-tech-300">Privacy Policy</a>
                <a href="{{ route('terms') }}" class="block hover:text-tech-300">Terms & Conditions</a>
                <a href="{{ route('sitemap') }}" class="block hover:text-tech-300">Sitemap</a>
            </div>
            <div class="space-y-2">
                <a href="https://wa.me/8801601934752" target="_blank" rel="noreferrer" class="block hover:text-tech-300">WhatsApp Order</a>
                <a href="https://www.facebook.com/maattechnologiesbd" target="_blank" rel="noreferrer" class="block hover:text-tech-300">Facebook</a>
                <a href="https://www.instagram.com/maattechnologiesbd" target="_blank" rel="noreferrer" class="block hover:text-tech-300">Instagram</a>
            </div>
        </div>
    </footer>
    <a href="https://wa.me/8801601934752" target="_blank" rel="noreferrer" aria-label="Ask on WhatsApp" class="fixed bottom-5 right-5 z-30 hidden min-h-11 items-center gap-2 rounded-full border border-emerald-500 bg-emerald-700 px-4 py-3 text-xs font-semibold text-white shadow-lg shadow-black/30 transition hover:bg-emerald-600 sm:inline-flex">Ask on WhatsApp</a>
    <script>window.lucide?.createIcons();</script>
    @stack('scripts')
</body>
</html>
