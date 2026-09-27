@extends('layouts.storefront')
@section('title', $product->seo_title ?: $product->name)
@section('meta_description', $product->seo_description ?: \Illuminate\Support\Str::limit($product->description, 150))

@php
    $gallery = collect($product->images ?? [])->filter()->values();
    if ($gallery->isEmpty() && $product->image) {
        $gallery = collect([$product->image]);
    }
@endphp

@section('content')
<main class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
    <a href="{{ route('products') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-tech-400 outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back to products</a>

    <div class="mt-5 grid gap-6 lg:grid-cols-[1.35fr_.65fr] lg:items-start">
        <div class="min-w-0">
            @if ($productShowcase)
                <x-product-showroom :product="$product" :showcase="$productShowcase" />
            @elseif ($gallery->isNotEmpty())
                <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(12rem,1fr)]">
                    <img id="product-main-image" src="{{ asset('storage/' . $gallery->first()) }}" alt="{{ $product->name }}" class="aspect-[4/3] w-full rounded-sm border border-cyber-border bg-[#0c1119] object-cover object-center">
                    @if ($gallery->count() > 1)
                        <div class="grid grid-cols-2 content-start gap-2">
                            @foreach ($gallery as $image)
                                <button type="button" class="product-thumb min-h-11 overflow-hidden rounded-sm border border-cyber-border bg-[#0c1119] outline-none focus-visible:ring-2 focus-visible:ring-tech-300" data-image="{{ asset('storage/' . $image) }}" aria-label="View product photograph {{ $loop->iteration }}"><img src="{{ asset('storage/' . $image) }}" alt="" class="aspect-square w-full object-cover" loading="lazy"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="grid aspect-[4/3] place-items-center rounded-sm border border-cyber-border bg-[#0c1119] text-tech-400"><i data-lucide="image-off" class="h-28 w-28" stroke-width="1"></i></div>
            @endif
        </div>

        <aside class="glass-panel h-fit p-6 lg:sticky lg:top-24">
            <p class="font-mono text-[10px] uppercase tracking-[.18em] text-tech-400">{{ $product->category->name ?? 'Catalog' }} · {{ $product->sku ?: 'UNIT-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p>
            <h1 class="mt-3 text-3xl font-bold leading-tight text-white">{{ $product->name }}</h1>
            <p class="mt-4 leading-7 text-slate-400">{{ $product->description }}</p>
            <div class="mt-6 border-t border-cyber-border pt-5">
                @if ($product->has_discount)
                    <p class="font-mono text-sm text-slate-500 line-through">BDT {{ number_format($product->price, 2) }}</p>
                    <p class="mt-1 font-mono text-3xl font-bold text-tech-300">BDT {{ number_format($product->final_price, 2) }}</p>
                    <p class="mt-2 text-xs text-slate-500">Current product discount: BDT {{ number_format($product->discount_amount, 2) }}</p>
                @else
                    <p class="font-mono text-3xl font-bold text-tech-300">BDT {{ number_format($product->final_price, 2) }}</p>
                @endif
                <p class="mt-3 text-sm text-slate-300">Availability: <span class="font-semibold {{ $product->stock > 0 ? 'text-tech-300' : 'text-rose-300' }}">{{ $product->stock > 0 ? 'In stock (' . $product->stock . ' available)' : 'Out of stock' }}</span></p>
            </div>
            <form method="POST" action="{{ route('cart.add', $product) }}" class="mt-6">
                @csrf
                <label class="block text-sm font-semibold text-slate-200">Quantity
                    <input type="number" name="quantity" min="1" max="{{ max(1, $product->stock) }}" value="1" {{ $product->stock < 1 ? 'disabled' : '' }} class="mt-2 min-h-11 w-full rounded-sm border border-cyber-border bg-[#090d14] px-3 py-3 text-white outline-none focus-visible:border-tech-400 focus-visible:ring-2 focus-visible:ring-tech-300">
                </label>
                <button {{ $product->stock < 1 ? 'disabled' : '' }} class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-sm border border-tech-400 bg-tech-600 px-4 py-3 text-sm font-semibold text-white outline-none hover:bg-tech-500 focus-visible:ring-2 focus-visible:ring-tech-300 disabled:cursor-not-allowed disabled:opacity-50"><i data-lucide="shopping-cart" class="h-4 w-4"></i>Add to Cart</button>
            </form>
            <form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="mt-3">@csrf<button class="flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-cyber-border px-4 py-3 text-sm font-semibold text-slate-300 outline-none hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="heart" class="h-4 w-4"></i>Save to Wishlist</button></form>
            <a href="https://wa.me/8801601934752?text={{ urlencode('I want to order ' . $product->name) }}" target="_blank" rel="noreferrer" class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-emerald-700 bg-emerald-950/40 px-4 py-3 text-sm font-semibold text-emerald-300 outline-none hover:bg-emerald-900/50 focus-visible:ring-2 focus-visible:ring-emerald-400"><i data-lucide="message-circle" class="h-4 w-4"></i>Ask on WhatsApp</a>
        </aside>
    </div>

    @if ($productShowcase && $gallery->isNotEmpty())
        <section class="mt-10" aria-labelledby="product-photos-heading">
            <div class="mb-4 flex items-end justify-between gap-3">
                <div><p class="font-mono text-[10px] uppercase tracking-[.18em] text-tech-400">Real product media</p><h2 id="product-photos-heading" class="mt-1 text-xl font-bold text-white">Product photographs</h2></div>
                <p class="text-xs text-slate-500">Photographs remain separate from the visual 3D preview.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(12rem,1fr)]">
                <img id="product-main-image" src="{{ asset('storage/' . $gallery->first()) }}" alt="{{ $product->name }}" class="aspect-[16/10] w-full rounded-sm border border-cyber-border bg-[#0c1119] object-cover object-center">
                <div class="grid grid-cols-2 content-start gap-2">
                    @foreach ($gallery as $image)
                        <button type="button" class="product-thumb min-h-11 overflow-hidden rounded-sm border border-cyber-border bg-[#0c1119] outline-none focus-visible:ring-2 focus-visible:ring-tech-300" data-image="{{ asset('storage/' . $image) }}" aria-label="View product photograph {{ $loop->iteration }}"><img src="{{ asset('storage/' . $image) }}" alt="" class="aspect-square w-full object-cover" loading="lazy"></button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($product->video_path)
        <section class="mt-8"><h2 class="mb-3 text-xl font-bold text-white">Product video</h2><video controls class="w-full rounded-sm border border-cyber-border" src="{{ asset('storage/' . $product->video_path) }}"></video></section>
    @endif

    <div class="mt-10 grid gap-6 lg:grid-cols-2">
        <section class="rounded-sm border border-cyber-border bg-[#0d121b] p-5" aria-labelledby="specification-heading">
            <h2 id="specification-heading" class="text-xl font-bold text-white">Product specifications</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($product->specs ?? [] as $key => $value)
                    <div class="border-t border-cyber-border pt-3"><dt class="font-mono text-[10px] uppercase text-slate-500">{{ $key }}</dt><dd class="mt-1 text-sm text-slate-200">{{ $value }}</dd></div>
                @empty
                    <div class="text-sm text-slate-500">No additional product specifications have been published.</div>
                @endforelse
            </dl>
        </section>
        <section class="rounded-sm border border-cyber-border bg-[#0d121b] p-5" aria-labelledby="reviews-heading">
            <h2 id="reviews-heading" class="text-xl font-bold text-white">Customer Reviews</h2>
            <div class="mt-4 space-y-3">
                @forelse ($product->reviews as $review)
                    <article class="border-b border-cyber-border pb-3 last:border-b-0 last:pb-0"><h3 class="text-sm font-semibold text-white">{{ $review->title }}</h3><p class="mt-1 text-xs text-tech-300">Rating: {{ $review->rating }}/5</p><p class="mt-2 text-sm text-slate-400">{{ $review->body }}</p></article>
                @empty
                    <p class="text-sm text-slate-500">No customer reviews have been published yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    @if ($relatedProducts->isNotEmpty())
        <section class="mt-12">
            <p class="font-mono text-[10px] uppercase tracking-[.18em] text-tech-400">More from this category</p><h2 class="mt-2 text-2xl font-bold text-white">Related products</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($relatedProducts as $related)
                    <article class="glass-panel overflow-hidden transition hover:-translate-y-1 hover:border-tech-600">
                        <a href="{{ route('products.show', $related->slug) }}" class="block aspect-[4/3] overflow-hidden bg-[#0d121b] outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300">
                            @if(!empty($related->images))<img src="{{ asset('storage/' . $related->images[0]) }}" alt="{{ $related->name }}" class="h-full w-full object-cover">@elseif($related->image)<img src="{{ asset('storage/' . $related->image) }}" alt="{{ $related->name }}" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-tech-400"><i data-lucide="image-off" class="h-16 w-16"></i></span>@endif
                        </a>
                        <div class="p-5"><a href="{{ route('products.show', $related->slug) }}" class="font-semibold text-white outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">{{ $related->name }}</a><p class="mt-3 font-mono text-sm text-tech-300">BDT {{ number_format($related->final_price, 2) }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection

@push('head')
<style>
    .product-showcase-grid { background-image: linear-gradient(rgb(45 212 191 / .06) 1px, transparent 1px), linear-gradient(90deg, rgb(45 212 191 / .06) 1px, transparent 1px); background-size: 28px 28px; }
    .product-showroom fieldset { min-inline-size: 0; }
    .product-showroom[data-showroom-state="ready"] [data-showroom-canvas] { opacity: 1; }
    .product-showroom[data-showroom-state="ready"] [data-showroom-poster] { opacity: 0; }
    .product-showroom[data-showroom-state="fallback"] [data-showroom-canvas] { display: none; }
    .showroom-button { display: inline-flex; min-width: 44px; min-height: 44px; align-items: center; justify-content: center; border: 1px solid rgb(45 212 191 / .7); background: rgb(9 13 19 / .82); color: #ccfbf1; backdrop-filter: blur(8px); outline: none; }
    .showroom-button:hover { background: rgb(13 148 136 / .8); }
    .showroom-button:focus-visible, .showroom-choice:focus-visible { box-shadow: 0 0 0 2px #5eead4; }
    .showroom-button:disabled, .showroom-choice:disabled { cursor: wait; opacity: .5; }
    .showroom-choice { min-height: 44px; border: 1px solid #334155; padding: .6rem .7rem; color: #cbd5e1; outline: none; }
    .showroom-choice[aria-pressed="true"] { border-color: #2dd4bf; background: rgb(13 148 136 / .28); color: white; }
    .showroom-part-label { position: absolute; transform: translate(-50%, -50%); border: 1px solid rgb(94 234 212 / .6); background: rgb(3 7 18 / .84); padding: .22rem .38rem; font: 500 9px/1.2 "DM Mono", monospace; color: #ccfbf1; white-space: nowrap; }
    details[open] > summary svg { transform: rotate(180deg); }
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('.product-thumb').forEach(function (button) {
        button.addEventListener('click', function () {
            var main = document.getElementById('product-main-image');
            if (main) main.src = button.dataset.image;
        });
    });
</script>
@endpush
