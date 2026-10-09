@extends('layouts.storefront')

@section('title', $storefrontSettings->home_meta_title)
@section('meta_description', $storefrontSettings->home_meta_description ?: $storefrontSettings->default_meta_description)

@section('content')
<main>
    @if($storefrontSlides->isNotEmpty())
        <x-cms-slideshow :slides="$storefrontSlides" />
    @endif

    <section class="relative overflow-hidden px-4 pb-16 pt-12 sm:px-6 lg:pb-24 lg:pt-20">
        <div class="absolute right-0 top-0 -z-10 h-full w-2/3 bg-[radial-gradient(circle_at_center,rgba(20,184,166,.07),transparent_66%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_.85fr] lg:items-center">
            <div class="relative z-10 lg:order-2">
                <p class="mb-6 inline-flex items-center gap-2 border border-tech-800 bg-tech-950/40 px-3 py-2 font-mono text-[10px] uppercase tracking-[.2em] text-tech-300">
                    <i class="status-dot h-2 w-2 bg-tech-400"></i>{{ $storefrontSettings->hero_badge }}
                </p>
                @if($heroProduct)
                    <p class="font-mono text-xs uppercase tracking-[.18em] text-slate-500">{{ $heroProduct->sku ?: 'MAAT Technologies BD' }}</p>
                    <h1 class="mt-3 max-w-2xl text-4xl font-extrabold leading-[1.08] text-white sm:text-5xl lg:text-6xl">{{ $storefrontSettings->hero_heading ?: $heroProduct->name }}</h1>
                    @if($storefrontSettings->hero_copy || $heroProduct->description)
                        <p class="mt-6 max-w-xl text-base leading-7 text-slate-400">{{ $storefrontSettings->hero_copy ?: \Illuminate\Support\Str::limit($heroProduct->description, 180) }}</p>
                    @endif
                    @if($heroProduct->has_discount)
                        <p class="mt-7 text-3xl font-extrabold text-amber-300">{{ $heroProduct->discountPercent() }}% off</p>
                    @endif
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('products.show', $heroProduct->slug) }}" class="inline-flex min-h-11 items-center gap-2 border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-semibold text-white outline-none transition hover:bg-tech-500 focus-visible:ring-2 focus-visible:ring-tech-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#090b10]">
                            {{ $storefrontSettings->hero_primary_label }}<i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="{{ route('products') }}" class="inline-flex min-h-11 items-center border border-cyber-border bg-cyber-panel/70 px-5 py-3 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-white focus-visible:ring-2 focus-visible:ring-tech-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#090b10]">{{ $storefrontSettings->hero_secondary_label }}</a>
                    </div>
                    @if($heroShowcase)
                        <p class="mt-5 flex items-center gap-2 text-xs text-slate-500"><i data-lucide="rotate-3d" class="h-4 w-4 text-tech-500"></i>{{ $storefrontSettings->hero_note }}</p>
                    @endif
                @else
                    <h1 class="max-w-2xl text-4xl font-extrabold leading-tight text-white sm:text-5xl">{{ $storefrontSettings->hero_heading ?: 'Lighting by '.$storefrontSettings->site_name }}</h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-slate-400">{{ $storefrontSettings->hero_copy ?: 'Browse the current published collection.' }}</p>
                    <a href="{{ route('products') }}" class="mt-8 inline-flex min-h-11 items-center gap-2 border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-semibold text-white">{{ $storefrontSettings->hero_secondary_label }}<i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                @endif
            </div>

            <div class="relative lg:order-1">
                @if($heroProduct)
                    <x-product-showcase :product="$heroProduct" :showcase="$heroShowcase" priority="initial" />
                @else
                    <div class="grid aspect-[5/4] place-items-center border border-cyber-border bg-[#090d13] text-tech-500 min-[480px]:min-h-[20rem]">
                        <i data-lucide="lamp-desk" class="h-24 w-24" stroke-width="1"></i>
                    </div>
                @endif
            </div>
        </div>
    </section>

</main>
@endsection

@push('head')
<style>
    .product-showcase-grid {
        background-image: linear-gradient(rgba(45, 212, 191, .04) 1px, transparent 1px), linear-gradient(90deg, rgba(45, 212, 191, .04) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(circle at 62% 48%, black, transparent 68%);
    }
    .product-showcase[data-showcase-state="ready"] [data-showcase-canvas] { opacity: 1; }
    .product-showcase[data-showcase-state="ready"] [data-showcase-poster] { opacity: 0; }
    .product-showcase[data-showcase-state="fallback"] [data-showcase-canvas] { display: none; }
    @media (prefers-reduced-motion: reduce) {
        .product-showcase, .product-showcase * { scroll-behavior: auto !important; }
    }
</style>
@endpush
