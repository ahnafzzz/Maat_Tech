@extends('layouts.storefront')

@section('title', 'Featured Lighting')
@section('meta_description', 'Explore featured lighting from MAAT Technologies BD.')

@section('content')
<main>
    <section class="relative overflow-hidden px-4 pb-16 pt-12 sm:px-6 lg:pb-24 lg:pt-20">
        <div class="absolute right-0 top-0 -z-10 h-full w-2/3 bg-[radial-gradient(circle_at_center,rgba(20,184,166,.07),transparent_66%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.82fr_1.18fr] lg:items-center">
            <div class="relative z-10">
                <p class="mb-6 inline-flex items-center gap-2 border border-tech-800 bg-tech-950/40 px-3 py-2 font-mono text-[10px] uppercase tracking-[.2em] text-tech-300">
                    <i class="status-dot h-2 w-2 bg-tech-400"></i>Featured lighting
                </p>
                @if($heroProduct)
                    <p class="font-mono text-xs uppercase tracking-[.18em] text-slate-500">{{ $heroProduct->sku ?: 'MAAT Technologies BD' }}</p>
                    <h1 class="mt-3 max-w-2xl text-4xl font-extrabold leading-[1.08] text-white sm:text-5xl lg:text-6xl">{{ $heroProduct->name }}</h1>
                    @if($heroProduct->description)
                        <p class="mt-6 max-w-xl text-base leading-7 text-slate-400">{{ \Illuminate\Support\Str::limit($heroProduct->description, 180) }}</p>
                    @endif
                    <p class="mt-7 font-mono text-2xl font-semibold text-white">BDT {{ number_format($heroProduct->final_price, 2) }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('products.show', $heroProduct->slug) }}" class="inline-flex min-h-11 items-center gap-2 border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-semibold text-white outline-none transition hover:bg-tech-500 focus-visible:ring-2 focus-visible:ring-tech-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#090b10]">
                            View Product<i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="{{ route('products') }}" class="inline-flex min-h-11 items-center border border-cyber-border bg-cyber-panel/70 px-5 py-3 text-sm font-semibold text-slate-200 outline-none transition hover:border-tech-600 hover:text-white focus-visible:ring-2 focus-visible:ring-tech-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#090b10]">Shop Lamps</a>
                    </div>
                    @if($heroShowcase)
                        <p class="mt-5 flex items-center gap-2 text-xs text-slate-500"><i data-lucide="rotate-3d" class="h-4 w-4 text-tech-500"></i>Automatic 3D preview · presentation view</p>
                    @endif
                @else
                    <h1 class="max-w-2xl text-4xl font-extrabold leading-tight text-white sm:text-5xl">Lighting by MAAT Technologies BD</h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-slate-400">Browse the current published collection.</p>
                    <a href="{{ route('products') }}" class="mt-8 inline-flex min-h-11 items-center gap-2 border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-semibold text-white">Shop Lamps<i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                @endif
            </div>

            <div class="relative">
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

    <section class="border-y border-cyber-border bg-cyber-panel/40 px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex items-center justify-between">
                <h2 class="flex items-center gap-2 font-mono text-sm font-bold text-white"><i data-lucide="layers" class="h-5 w-5 text-tech-400"></i>Categories</h2>
                <a href="{{ route('products') }}" class="flex min-h-11 items-center gap-1 text-xs font-semibold text-tech-400 outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">View all<i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @forelse($categories as $category)
                    <a href="{{ route('products', ['category' => $category->slug]) }}" class="glass-panel group p-5 outline-none transition hover:border-tech-600 focus-visible:ring-2 focus-visible:ring-tech-300">
                        <i data-lucide="{{ $loop->index === 0 ? 'lamp-desk' : ($loop->index === 1 ? 'lightbulb' : 'settings') }}" class="mb-4 h-7 w-7 text-tech-400 transition group-hover:scale-110"></i>
                        <p class="text-sm font-semibold text-white">{{ $category->name }}</p>
                        <p class="mt-1 font-mono text-[10px] text-slate-500">{{ $category->products_count ?? 0 }} UNITS</p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Catalog categories are being initialized.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section id="featured" class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="mb-8 flex items-end justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[.18em] text-tech-400">Current collection</p>
                <h2 class="mt-2 text-2xl font-bold text-white">Featured products</h2>
            </div>
            <a href="{{ route('products') }}" class="grid h-11 w-11 place-items-center border border-cyber-border text-slate-400 outline-none hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300" title="Browse all products"><i data-lucide="grid-3x3" class="h-4 w-4"></i></a>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @forelse($featuredProducts as $product)
                <article class="glass-panel group overflow-hidden transition hover:-translate-y-1 hover:border-tech-600">
                    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-[4/3] overflow-hidden bg-[#0c1119] text-tech-400 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300">
                        @if(!empty($product->images))
                            <img src="{{ asset('storage/' . $product->images[0]) }}" alt="{{ $product->name }}" class="block h-full w-full object-cover object-center transition duration-300 group-hover:scale-105" loading="lazy">
                        @elseif($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="block h-full w-full object-cover object-center transition duration-300 group-hover:scale-105" loading="lazy">
                        @else
                            <span class="grid h-full place-items-center"><i data-lucide="image-off" class="h-20 w-20" stroke-width="1"></i></span>
                        @endif
                    </a>
                    <div class="p-5">
                        <p class="font-mono text-[10px] text-tech-400">{{ $product->sku ?: 'MOD.' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p>
                        <a href="{{ route('products.show', $product->slug) }}" class="mt-2 block font-semibold text-white outline-none transition hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">{{ $product->name }}</a>
                        <p class="mt-2 min-h-10 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($product->description, 88) }}</p>
                        <div class="mt-4 flex items-center justify-between border-t border-cyber-border pt-4">
                            <strong class="font-mono text-lg text-white">BDT {{ number_format($product->final_price, 2) }}</strong>
                            <form method="POST" action="{{ route('cart.add', $product) }}">
                                @csrf
                                <button title="Add {{ $product->name }} to cart" class="grid h-11 w-11 place-items-center border border-tech-600 text-tech-300 outline-none transition hover:bg-tech-600 hover:text-white focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="plus" class="h-4 w-4"></i></button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">Featured products will appear here shortly.</p>
            @endforelse
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
