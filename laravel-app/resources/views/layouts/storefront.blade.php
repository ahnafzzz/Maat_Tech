<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $storefrontSettings->site_name) | {{ $storefrontSettings->site_name }}</title>
    <meta name="description" content="@yield('meta_description', $storefrontSettings->default_meta_description)">
    <meta property="og:title" content="@yield('title', $storefrontSettings->site_name) | {{ $storefrontSettings->site_name }}">
    <meta property="og:description" content="@yield('meta_description', $storefrontSettings->default_meta_description)">
    <meta property="og:image" content="{{ $storefrontSettings->logoUrl() }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --color-tech-300: {{ $storefrontSettings->color('accent_color', '#5eead4') }};
            --color-tech-400: {{ $storefrontSettings->color('accent_color', '#2dd4bf') }};
            --color-tech-500: {{ $storefrontSettings->color('primary_color', '#14b8a6') }};
            --color-tech-600: {{ $storefrontSettings->color('primary_color', '#0d9488') }};
            --color-cyber-dark: {{ $storefrontSettings->color('background_color', '#090b10') }};
            --color-cyber-panel: {{ $storefrontSettings->color('panel_color', '#121722') }};
        }
        body { background: {{ $storefrontSettings->color('background_color', '#090b10') }}; }
    </style>
    <script type="application/ld+json" nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $storefrontSettings->site_name,
            'url' => url('/'),
            'logo' => $storefrontSettings->logoUrl(),
            'email' => $storefrontSettings->support_email,
            'telephone' => $storefrontSettings->phone_display,
            'sameAs' => array_values(array_filter([$storefrontSettings->facebook_url, $storefrontSettings->instagram_url])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @if (env('FACEBOOK_PIXEL_ID'))
        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
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
            <a href="{{ route('home') }}" class="flex min-h-11 shrink-0 items-center gap-3 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-tech-300" aria-label="{{ $storefrontSettings->site_name }} home">
                <img src="{{ $storefrontSettings->logoUrl() }}" alt="{{ $storefrontSettings->logo_alt }}" class="brand-logo h-11 w-16 shrink-0 object-contain">
                <span class="hidden leading-none sm:block"><strong class="block text-base font-extrabold tracking-tight text-white">{{ $storefrontSettings->site_name }}</strong>@if($storefrontSettings->tagline)<small class="font-mono text-[9px] tracking-[.18em] text-tech-400">{{ $storefrontSettings->tagline }}</small>@endif</span>
            </a>
            <div class="hidden max-w-2xl flex-1 items-center justify-center gap-2 md:flex">
                @foreach($headerLinks as $link)
                    <a href="{{ $link->href() }}" @if($link->open_new_tab) target="_blank" rel="noreferrer" @endif class="flex min-h-11 items-center justify-center gap-2 rounded-sm border border-cyber-border bg-cyber-panel/60 px-4 py-2 text-sm font-semibold text-slate-300 outline-none transition hover:border-tech-600 hover:text-white focus-visible:ring-2 focus-visible:ring-tech-300"><span>{{ $link->url === '/products' ? $storefrontSettings->shop_label : $link->label }}</span></a>
                @endforeach
            </div>
            <div class="flex items-center gap-1 sm:gap-2">
                @if($storefrontSettings->wishlist_enabled)<a href="{{ route('wishlist.index') }}" aria-label="Your wishlist" title="Your wishlist" class="grid h-11 w-11 place-items-center rounded-sm text-slate-400 outline-none transition hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="heart" class="h-5 w-5"></i></a>@endif
                @if($storefrontSettings->cart_enabled)<a href="{{ route('cart.index') }}" aria-label="Your cart" title="Your cart" class="grid h-11 w-11 place-items-center rounded-sm text-slate-400 outline-none transition hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="shopping-cart" class="h-5 w-5"></i></a>@endif
                <span class="mx-1 hidden h-6 w-px bg-cyber-border sm:block"></span>
                @auth
                    <a href="{{ route('dashboard') }}" class="flex min-h-11 items-center gap-2 rounded-sm border border-cyber-border px-3 py-2 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="user" class="h-4 w-4"></i><span class="hidden sm:inline">{{ $storefrontSettings->account_label }}</span></a>
                @else
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center gap-2 rounded-sm border border-cyber-border px-3 py-2 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="log-in" class="h-4 w-4"></i><span class="hidden sm:inline">{{ $storefrontSettings->sign_in_label }}</span></a>
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
            <span>{{ $storefrontSettings->footer_copyright }}</span>
            <span class="flex items-center gap-2 text-tech-500"><i class="status-dot h-2 w-2 bg-tech-400"></i>{{ $storefrontSettings->footer_status }}</span>
            @if($storefrontSettings->support_email)<span>{{ $storefrontSettings->footer_support_label }}: {{ $storefrontSettings->support_email }}</span>@endif
        </div>
        <div class="mx-auto grid max-w-7xl gap-3 border-t border-cyber-border px-4 py-5 text-xs text-slate-400 sm:grid-cols-2 lg:grid-cols-4 sm:px-6">
            @foreach(range(1, 3) as $column)
                <div class="space-y-2">
                    @foreach($footerLinks->get($column, collect()) as $link)<a href="{{ $link->href() }}" @if($link->open_new_tab) target="_blank" rel="noreferrer" @endif class="block hover:text-tech-300">{{ $link->label }}</a>@endforeach
                </div>
            @endforeach
            <div class="space-y-2">
                @foreach($footerLinks->get(4, collect()) as $link)<a href="{{ $link->href() }}" @if($link->open_new_tab) target="_blank" rel="noreferrer" @endif class="block hover:text-tech-300">{{ $link->label }}</a>@endforeach
                @if($storefrontSettings->whatsappUrl())<a href="{{ $storefrontSettings->whatsappUrl() }}" target="_blank" rel="noreferrer" class="block hover:text-tech-300">WhatsApp Order</a>@endif
                @if($storefrontSettings->facebook_url)<a href="{{ $storefrontSettings->facebook_url }}" target="_blank" rel="noreferrer" class="block hover:text-tech-300">Facebook</a>@endif
                @if($storefrontSettings->instagram_url)<a href="{{ $storefrontSettings->instagram_url }}" target="_blank" rel="noreferrer" class="block hover:text-tech-300">Instagram</a>@endif
            </div>
        </div>
    </footer>
    @if($storefrontSettings->whatsappUrl())<a href="{{ $storefrontSettings->whatsappUrl() }}" target="_blank" rel="noreferrer" aria-label="{{ $storefrontSettings->whatsapp_cta_label }}" class="fixed bottom-5 right-5 z-30 hidden min-h-11 items-center gap-2 rounded-full border border-emerald-500 bg-emerald-700 px-4 py-3 text-xs font-semibold text-white shadow-lg shadow-black/30 transition hover:bg-emerald-600 sm:inline-flex">{{ $storefrontSettings->whatsapp_cta_label }}</a>@endif
    @stack('scripts')
</body>
</html>
