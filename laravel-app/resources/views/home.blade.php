@extends('layouts.storefront')

@section('title', 'Precision Illumination Systems')
@section('meta_description', 'Precision mechanical arm lighting systems from MAAT TECHNOLOGIE BD for architects, engineers, and creators in Bangladesh.')

@section('content')
<main>
    <section class="relative overflow-hidden px-4 pb-16 pt-16 sm:px-6 lg:pb-24 lg:pt-24">
        <div class="absolute right-0 top-0 -z-10 h-full w-1/2 bg-[radial-gradient(circle_at_center,rgba(20,184,166,.18),transparent_65%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="mb-6 inline-flex items-center gap-2 border border-tech-800 bg-tech-950/50 px-3 py-1 text-xs font-mono tracking-wider text-tech-300"><i class="status-dot h-2 w-2 bg-tech-400"></i>SYSTEM.ONLINE v2.4</p>
                <h1 class="max-w-2xl text-4xl font-extrabold leading-tight text-white sm:text-5xl">Precision <span class="text-tech-300">Mechanical</span><br>Illumination</h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-slate-400">Industrial-grade articulated arm lighting systems, engineered for architects, engineers, and creators who demand positional accuracy.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('products') }}" class="inline-flex items-center gap-2 border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-mono text-white transition hover:bg-tech-500">INITIATE_BROWSE<i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                    <a href="#featured" class="border border-cyber-border bg-cyber-panel/70 px-5 py-3 text-sm font-mono text-slate-300 transition hover:border-tech-600 hover:text-white">VIEW_UNITS</a>
                </div>
                <div class="mt-12 grid max-w-xl grid-cols-3 gap-4 border-t border-cyber-border pt-6">
                    <div><strong class="block font-mono text-2xl text-white">04</strong><span class="mt-1 block text-[10px] font-mono text-slate-500">ARTICULATION_AXES</span></div>
                    <div><strong class="block font-mono text-2xl text-white">CNC</strong><span class="mt-1 block text-[10px] font-mono text-slate-500">MILLED_JOINTS</span></div>
                    <div><strong class="block font-mono text-2xl text-white">24V</strong><span class="mt-1 block text-[10px] font-mono text-slate-500">DC_POWER_SYS</span></div>
                </div>
            </div>
            <article class="glass-panel overflow-hidden p-2">
                <div class="relative grid aspect-square place-items-center overflow-hidden bg-[radial-gradient(circle_at_center,rgba(20,184,166,.18),transparent_65%),linear-gradient(135deg,#171d29,#050609)]">
                    @php($heroProduct = $featuredProducts->first())
                    <span class="absolute left-4 top-4 font-mono text-[10px] text-tech-400">{{ $heroProduct?->sku ?: 'MAAT.FEATURED' }}</span>
                    @if($heroProduct && !empty($heroProduct->images))
                        <img src="{{ asset('storage/' . $heroProduct->images[0]) }}" alt="{{ $heroProduct->name }}" class="h-full w-full object-cover">
                    @elseif($heroProduct && $heroProduct->image)
                        <img src="{{ asset('storage/' . $heroProduct->image) }}" alt="{{ $heroProduct->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center text-tech-400"><i data-lucide="image-off" class="h-24 w-24 sm:h-28 sm:w-28" stroke-width="1"></i></div>
                    @endif
                    <span class="absolute bottom-4 right-4 bg-black/40 px-2 py-1 font-mono text-[10px] text-slate-300">FEATURED_UNIT</span>
                </div>
                @if($featuredProducts->isNotEmpty())
                    <div class="flex items-center justify-between bg-[#0e131d] p-4"><div><p class="text-sm font-semibold text-white">{{ $heroProduct->name }}</p><p class="mt-1 font-mono text-xs text-tech-400">BDT {{ number_format($heroProduct->price) }}</p></div><a href="{{ route('products.show', $heroProduct->slug) }}" title="View {{ $heroProduct->name }}" class="grid h-9 w-9 place-items-center border border-tech-400 bg-tech-600 text-white transition hover:bg-tech-500"><i data-lucide="plus" class="h-4 w-4"></i></a></div>
                @endif
            </article>
        </div>
    </section>
    <section class="border-y border-cyber-border bg-cyber-panel/40 px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl"><div class="mb-8 flex items-center justify-between"><h2 class="flex items-center gap-2 font-mono text-sm font-bold text-white"><i data-lucide="layers" class="h-5 w-5 text-tech-400"></i>COMPONENT_CATEGORIES</h2><a href="{{ route('products') }}" class="flex items-center gap-1 text-xs font-mono text-tech-400 hover:text-tech-300">VIEW_ALL<i data-lucide="chevron-right" class="h-4 w-4"></i></a></div><div class="grid grid-cols-2 gap-3 md:grid-cols-4">@forelse($categories as $category)<a href="{{ route('products') }}" class="glass-panel group p-5 transition hover:border-tech-600"><i data-lucide="{{ $loop->index === 0 ? 'lamp-desk' : ($loop->index === 1 ? 'lightbulb' : 'settings') }}" class="mb-4 h-7 w-7 text-tech-400 transition group-hover:scale-110"></i><p class="text-sm font-semibold text-white">{{ $category->name }}</p><p class="mt-1 font-mono text-[10px] text-slate-500">{{ $category->products_count ?? 0 }} UNITS</p></a>@empty<p class="text-sm text-slate-500">Catalog categories are being initialized.</p>@endforelse</div></div>
    </section>
    <section id="featured" class="mx-auto max-w-7xl px-4 py-16 sm:px-6"><div class="mb-8 flex items-end justify-between"><div><p class="font-mono text-xs text-tech-400">CURATED_CATALOG</p><h2 class="mt-2 font-mono text-2xl font-bold text-white">FEATURED_UNITS</h2></div><a href="{{ route('products') }}" class="border border-cyber-border p-2 text-slate-400 hover:border-tech-600 hover:text-tech-300" title="Browse all products"><i data-lucide="grid-3x3" class="h-4 w-4"></i></a></div><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($featuredProducts as $product)<article class="glass-panel group overflow-hidden transition hover:-translate-y-1 hover:border-tech-600"><a href="{{ route('products.show', $product->slug) }}" class="grid aspect-[4/3] place-items-center bg-[#0c1119] text-tech-400"><i data-lucide="lamp-desk" class="h-20 w-20 transition duration-300 group-hover:scale-110" stroke-width="1"></i></a><div class="p-5"><p class="font-mono text-[10px] text-tech-400">{{ $product->sku ?: 'MOD.' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p><a href="{{ route('products.show', $product->slug) }}" class="mt-2 block font-semibold text-white transition hover:text-tech-300">{{ $product->name }}</a><p class="mt-2 min-h-10 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($product->description, 88) }}</p><div class="mt-4 flex items-center justify-between border-t border-cyber-border pt-4"><strong class="font-mono text-lg text-white">BDT {{ number_format($product->price) }}</strong><form method="POST" action="{{ route('cart.add', $product) }}">@csrf<button title="Add {{ $product->name }} to cart" class="grid h-9 w-9 place-items-center border border-tech-600 text-tech-300 transition hover:bg-tech-600 hover:text-white"><i data-lucide="plus" class="h-4 w-4"></i></button></form></div></div></article>@empty<p class="text-sm text-slate-500">Featured units will appear here shortly.</p>@endforelse</div></section>
    <section id="featured" class="mx-auto max-w-7xl px-4 py-16 sm:px-6"><div class="mb-8 flex items-end justify-between"><div><p class="font-mono text-xs text-tech-400">CURATED_CATALOG</p><h2 class="mt-2 font-mono text-2xl font-bold text-white">FEATURED_UNITS</h2></div><a href="{{ route('products') }}" class="border border-cyber-border p-2 text-slate-400 hover:border-tech-600 hover:text-tech-300" title="Browse all products"><i data-lucide="grid-3x3" class="h-4 w-4"></i></a></div><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($featuredProducts as $product)<article class="glass-panel group overflow-hidden transition hover:-translate-y-1 hover:border-tech-600"><a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-[4/3] overflow-hidden bg-[#0c1119] text-tech-400">@if(!empty($product->images))<img src="{{ asset('storage/' . $product->images[0]) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">@elseif($product->image)<img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">@else<div class="grid h-full place-items-center"><i data-lucide="image-off" class="h-20 w-20" stroke-width="1"></i></div>@endif</a><div class="p-5"><p class="font-mono text-[10px] text-tech-400">{{ $product->sku ?: 'MOD.' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p><a href="{{ route('products.show', $product->slug) }}" class="mt-2 block font-semibold text-white transition hover:text-tech-300">{{ $product->name }}</a><p class="mt-2 min-h-10 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($product->description, 88) }}</p><div class="mt-4 flex items-center justify-between border-t border-cyber-border pt-4"><strong class="font-mono text-lg text-white">BDT {{ number_format($product->final_price) }}</strong><form method="POST" action="{{ route('cart.add', $product) }}">@csrf<button title="Add {{ $product->name }} to cart" class="grid h-9 w-9 place-items-center border border-tech-600 text-tech-300 transition hover:bg-tech-600 hover:text-white"><i data-lucide="plus" class="h-4 w-4"></i></button></form></div></div></article>@empty<p class="text-sm text-slate-500">Featured units will appear here shortly.</p>@endforelse</div></section>
</main>
@endsection
