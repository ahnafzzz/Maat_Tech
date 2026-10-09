@extends('layouts.storefront')
@section('title', $product->seo_title ?: $product->name)
@section('meta_description', $product->seo_description ?: \Illuminate\Support\Str::limit($product->description, 150))

@php
    $gallery = collect($product->galleryImageUrls());
    $videoUrl = $product->videoUrl();
    $purchaseVariants = collect($product->purchasableVariants());
    $defaultVariant = $purchaseVariants->first(fn ($variant) => $variant['available'] && $variant['stock'] > 0);
@endphp

@section('content')
<main class="mx-auto max-w-7xl px-4 pb-32 pt-10 sm:px-6 lg:pb-10">
    <a href="{{ route('products') }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-tech-400 outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back to products</a>

    <div class="mt-5 grid gap-6 lg:grid-cols-[1.35fr_.65fr] lg:items-start">
        <div class="min-w-0">
            @if ($productShowcase)
                <x-product-showroom :product="$product" :showcase="$productShowcase" />
            @elseif ($gallery->isNotEmpty() || $videoUrl)
                @php($initialMedia = $gallery->isNotEmpty() ? 'photo-0' : 'video')
                <section data-product-media-gallery data-active-media="{{ $initialMedia }}" aria-label="{{ $product->name }} media gallery">
                    <div class="relative aspect-[4/3] overflow-hidden rounded-sm border border-cyber-border bg-[#f2efe9]">
                        @foreach ($gallery as $image)
                            <div @if(!$loop->first) hidden @endif data-media-panel="photo-{{ $loop->index }}" class="absolute inset-0 grid place-items-center p-3 sm:p-6">
                                <img data-media-image @if($loop->first) src="{{ $image }}" @else data-src="{{ $image }}" @endif alt="{{ $product->name }} photograph {{ $loop->iteration }}" class="h-full w-full object-contain object-center">
                                <div hidden data-media-fallback class="absolute inset-0 grid place-items-center bg-[#0c1119] text-center text-sm text-slate-400">This photograph could not be loaded.</div>
                            </div>
                        @endforeach
                        @if ($videoUrl)
                            <div @if($initialMedia !== 'video') hidden @endif data-media-panel="video" class="absolute inset-0 grid place-items-center bg-black p-2 sm:p-4">
                                <video data-media-video data-src="{{ $videoUrl }}" controls preload="none" playsinline class="h-full w-full object-contain" aria-label="{{ $product->name }} product video"></video>
                                <div hidden data-media-fallback class="absolute inset-0 grid place-items-center bg-[#0c1119] text-center text-sm text-slate-400">This video could not be loaded.</div>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-2" role="tablist" aria-label="Product media">
                        @foreach ($gallery as $image)
                            <button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-media-select="photo-{{ $loop->index }}" class="media-thumbnail shrink-0 {{ $loop->first ? 'border-tech-400 bg-tech-950/50' : 'border-cyber-border bg-[#0c1119]' }}">
                                <img src="{{ $image }}" alt="" class="h-14 w-16 object-cover" loading="lazy"><span class="sr-only">Product photograph {{ $loop->iteration }}</span>
                            </button>
                        @endforeach
                        @if ($videoUrl)
                            <button type="button" role="tab" aria-selected="{{ $initialMedia === 'video' ? 'true' : 'false' }}" data-media-select="video" class="media-thumbnail relative shrink-0 {{ $initialMedia === 'video' ? 'border-tech-400 bg-tech-950/50' : 'border-cyber-border bg-[#0c1119]' }} text-white">
                                <span class="grid h-14 w-16 place-items-center"><i data-lucide="play" class="h-6 w-6"></i></span><span class="sr-only">Product video</span>
                            </button>
                        @endif
                    </div>
                </section>
            @else
                <div class="grid aspect-[4/3] place-items-center rounded-sm border border-cyber-border bg-[#0c1119] text-tech-400"><i data-lucide="image-off" class="h-28 w-28" stroke-width="1"></i></div>
            @endif
        </div>

        <aside class="glass-panel h-fit p-6 lg:sticky lg:top-24">
            <p class="font-mono text-[10px] uppercase tracking-[.18em] text-tech-400">{{ $product->category->name ?? 'Catalog' }} · {{ $product->sku ?: 'UNIT-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p>
            <h1 class="mt-3 text-3xl font-bold leading-tight text-white">{{ $product->name }}</h1>
            <p class="mt-4 leading-7 text-slate-400">{{ $product->description }}</p>
            <a href="#product-details" class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-tech-300 outline-none hover:text-tech-200 focus-visible:ring-2 focus-visible:ring-tech-300">View published product details<i data-lucide="arrow-down" class="h-4 w-4"></i></a>
            <div class="mt-6 border-t border-cyber-border pt-5">
                @if ($product->has_discount)
                    <p class="font-mono text-base text-slate-500 line-through">৳{{ number_format($product->price, 0) }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-3"><p class="font-mono text-3xl font-bold text-tech-300">৳{{ number_format($product->final_price, 0) }}</p><span class="border border-amber-500/60 bg-amber-950/40 px-2 py-1 text-xs font-bold text-amber-300">{{ $product->discountPercent() }}% OFF</span></div>
                    <p class="mt-2 text-sm text-slate-400">You save ৳{{ number_format($product->discount_amount, 0) }}</p>
                @else
                    <p class="font-mono text-3xl font-bold text-tech-300">BDT {{ number_format($product->final_price, 2) }}</p>
                @endif
                <p class="mt-4 font-semibold text-emerald-300">Free delivery all across Bangladesh.</p>
            </div>
            <form id="product-purchase-form" method="POST" action="{{ route('cart.add', $product) }}" class="mt-6" data-submit-once>
                @csrf
                @if ($purchaseVariants->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-200">Color: <span data-selected-color>{{ $defaultVariant['label'] ?? 'Unavailable' }}</span></legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ($purchaseVariants as $variant)
                                @php($available = $variant['available'] && $variant['stock'] > 0)
                                <label @class(['flex min-h-12 items-center gap-3 border px-3 py-2 text-sm', 'cursor-pointer border-cyber-border text-slate-200 hover:border-tech-500' => $available, 'cursor-not-allowed border-slate-800 text-slate-600' => !$available])>
                                    <input type="radio" name="variant_key" value="{{ $variant['key'] }}" data-purchase-variant data-variant-label="{{ $variant['label'] }}" data-variant-stock="{{ $variant['stock'] }}" data-variant-finish="{{ in_array($variant['key'], ['black', 'white'], true) ? $variant['key'] : '' }}" @checked(($defaultVariant['key'] ?? null) === $variant['key']) @disabled(!$available) class="accent-teal-500">
                                    <span>{{ $variant['label'] }}</span><span class="ml-auto text-xs">{{ $available ? $variant['stock'].' available' : 'Unavailable' }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <label class="block text-sm font-semibold text-slate-200">Quantity
                    <input data-purchase-quantity type="number" name="quantity" min="1" max="{{ max(1, $defaultVariant['stock'] ?? $product->stock) }}" value="1" {{ $product->stock < 1 || ($purchaseVariants->isNotEmpty() && !$defaultVariant) ? 'disabled' : '' }} class="mt-2 min-h-11 w-full rounded-sm border border-cyber-border bg-[#090d14] px-3 py-3 text-white outline-none focus-visible:border-tech-400 focus-visible:ring-2 focus-visible:ring-tech-300">
                </label>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <button {{ $product->stock < 1 ? 'disabled' : '' }} formaction="{{ route('cart.add', $product) }}" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-sm border border-tech-400 bg-tech-600 px-4 py-3 text-sm font-semibold text-white outline-none hover:bg-tech-500 focus-visible:ring-2 focus-visible:ring-tech-300 disabled:cursor-not-allowed disabled:opacity-50"><i data-lucide="shopping-cart" class="h-4 w-4"></i>Add to Cart</button>
                    <button {{ $product->stock < 1 ? 'disabled' : '' }} formaction="{{ route('buy-now', $product) }}" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-sm border border-white bg-white px-4 py-3 text-sm font-semibold text-slate-950 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-tech-300 disabled:cursor-not-allowed disabled:opacity-50"><i data-lucide="zap" class="h-4 w-4"></i>Buy Now</button>
                </div>
            </form>
            <form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="mt-3">@csrf<button class="flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-cyber-border px-4 py-3 text-sm font-semibold text-slate-300 outline-none hover:border-tech-600 hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300"><i data-lucide="heart" class="h-4 w-4"></i>Save to Wishlist</button></form>
            @if($storefrontSettings->whatsappUrl())<a href="{{ $storefrontSettings->whatsappUrl('I want to order '.$product->name) }}" target="_blank" rel="noreferrer" class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-emerald-700 bg-emerald-950/40 px-4 py-3 text-sm font-semibold text-emerald-300 outline-none hover:bg-emerald-900/50 focus-visible:ring-2 focus-visible:ring-emerald-400"><i data-lucide="message-circle" class="h-4 w-4"></i>{{ $storefrontSettings->whatsapp_cta_label }}</a>@endif
        </aside>
    </div>

    <div id="product-details" class="mt-10 grid scroll-mt-24 gap-6 lg:grid-cols-2">
        <section class="min-w-0 rounded-sm border border-cyber-border bg-[#0d121b] p-5" aria-labelledby="specification-heading">
            <h2 id="specification-heading" class="text-xl font-bold text-white">Product specifications</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $product->description }}</p>
            @if (!empty($product->specs))
                <div class="mt-4 max-w-full overflow-hidden border border-cyber-border">
                    <table class="w-full table-fixed border-collapse text-left text-sm">
                        <caption class="sr-only">Published specifications for {{ $product->name }}</caption>
                        <tbody>
                            @foreach ($product->specs as $label => $value)
                                <tr class="border-b border-cyber-border last:border-b-0">
                                    <th scope="row" class="w-2/5 break-words bg-slate-950/45 px-3 py-3 font-mono text-[10px] uppercase tracking-[.08em] text-slate-400 sm:px-4">{{ $label }}</th>
                                    <td class="break-words px-3 py-3 text-slate-200 sm:px-4">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-500">No additional product specifications have been published.</p>
            @endif
        </section>
        <section class="min-w-0 rounded-sm border border-cyber-border bg-[#0d121b] p-5" aria-labelledby="reviews-heading">
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
                            <img src="{{ $related->primaryImageUrl() }}" alt="{{ $related->name }}" class="h-full w-full object-contain" loading="lazy">
                        </a>
                        <div class="p-5"><a href="{{ route('products.show', $related->slug) }}" class="font-semibold text-white outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">{{ $related->name }}</a><p class="mt-3 font-mono text-sm text-tech-300">BDT {{ number_format($related->final_price, 2) }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-cyber-border bg-[#090d14]/95 px-4 py-3 shadow-[0_-12px_35px_rgb(0_0_0/.45)] backdrop-blur lg:hidden" aria-label="Mobile purchase action">
    <div class="mx-auto flex max-w-7xl items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-white">{{ $product->name }}</p>
            <p class="font-mono text-sm text-tech-300">BDT {{ number_format($product->final_price, 2) }}</p>
        </div>
        <button type="submit" form="product-purchase-form" {{ $product->stock < 1 ? 'disabled' : '' }} class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-sm border border-tech-400 bg-tech-600 px-5 py-3 text-sm font-semibold text-white outline-none hover:bg-tech-500 focus-visible:ring-2 focus-visible:ring-tech-300 disabled:cursor-not-allowed disabled:opacity-50">
            <i data-lucide="shopping-cart" class="h-4 w-4"></i>{{ $product->stock > 0 ? 'Add to Cart' : 'Out of Stock' }}
        </button>
    </div>
</div>
@endsection

@push('head')
<style>
    .product-showcase-grid { background-image: linear-gradient(rgb(45 212 191 / .03) 1px, transparent 1px), linear-gradient(90deg, rgb(45 212 191 / .03) 1px, transparent 1px); background-size: 28px 28px; }
    .product-showroom fieldset { min-inline-size: 0; }
    .product-showroom[data-showroom-state="ready"] [data-showroom-canvas] { opacity: 1; }
    .product-showroom[data-showroom-state="ready"] [data-showroom-poster] { opacity: 0; }
    .product-showroom[data-showroom-state="fallback"] [data-showroom-canvas] { display: none; }
    .product-showroom[data-showroom-state="context-lost"] [data-showroom-canvas] { opacity: 0; }
    .product-showroom[data-showroom-state="fallback"] .showroom-retry,
    .product-showroom[data-showroom-state="context-lost"] .showroom-retry { display: inline-flex; }
    .showroom-button { display: inline-flex; min-width: 44px; min-height: 44px; align-items: center; justify-content: center; border: 1px solid rgb(45 212 191 / .7); background: rgb(9 13 19 / .82); color: #ccfbf1; backdrop-filter: blur(8px); outline: none; }
    .showroom-button:hover { background: rgb(13 148 136 / .8); }
    .showroom-button:focus-visible, .showroom-choice:focus-visible { box-shadow: 0 0 0 2px #5eead4; }
    .showroom-button:disabled, .showroom-choice:disabled { cursor: wait; opacity: .5; }
    .showroom-choice { min-height: 44px; border: 1px solid #334155; padding: .6rem .7rem; color: #cbd5e1; outline: none; }
    .showroom-choice[aria-pressed="true"] { border-color: #2dd4bf; background: rgb(13 148 136 / .28); color: white; }
    .showroom-part-label { position: absolute; min-height: 44px; transform: translate(-50%, -50%); border: 1px solid rgb(94 234 212 / .6); background: rgb(3 7 18 / .84); padding: .5rem .65rem; font: 500 9px/1.2 "DM Mono", monospace; color: #ccfbf1; white-space: nowrap; pointer-events: auto; outline: none; }
    .showroom-part-label:focus-visible { box-shadow: 0 0 0 2px #5eead4; }
    details[open] > summary svg { transform: rotate(180deg); }
</style>
@endpush
