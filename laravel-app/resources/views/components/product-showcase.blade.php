@props([
    'product',
    'showcase' => null,
    'priority' => 'lazy',
])

@php
    $productImage = !empty($product->images)
        ? asset('storage/' . $product->images[0])
        : ($product->image ? asset('storage/' . $product->image) : asset('images/brand/maat-tech-mark.png'));
    $poster = $showcase ? asset($showcase['poster']) : $productImage;
    $productUrl = route('products.show', $product->slug);
@endphp

<div
    class="product-showcase group relative block aspect-[5/4] overflow-hidden rounded-sm border border-slate-400/70 bg-slate-200 outline-none transition focus-visible:border-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#090b10] min-[480px]:min-h-[20rem] sm:min-h-[28rem] lg:aspect-[6/5]"
    data-product-showcase
    data-showcase-priority="{{ $priority }}"
    @if($showcase)
        data-showcase-model="{{ $showcase['model_id'] }}"
        data-showcase-manifest="{{ asset($showcase['manifest']) }}"
    @endif
>
    <a
        href="{{ $productUrl }}"
        class="absolute inset-0 block outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-tech-300"
        aria-label="View {{ $product->name }} product details"
        data-showcase-link
    >
        <span class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_62%_30%,#aab3ba_0%,#828e98_48%,#59656f_100%)]"></span>
        <img
            src="{{ $poster }}"
            data-showcase-poster
            data-fallback-src="{{ $productImage }}"
            alt="{{ $product->name }}"
            class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center transition-opacity duration-500 motion-reduce:transition-none"
            @if($priority !== 'initial') loading="lazy" @else fetchpriority="high" @endif
        >
        @if($showcase)
            <canvas
                data-showcase-canvas
                class="pointer-events-none absolute inset-0 h-full w-full opacity-0 transition-opacity duration-500 motion-reduce:transition-none"
                aria-hidden="true"
            ></canvas>
        @endif
        <span class="pointer-events-none absolute inset-x-4 top-4 flex items-center justify-between gap-3 font-mono text-[10px] uppercase tracking-[.16em] text-slate-700">
            <span>{{ $product->sku ?: 'MAAT FEATURED' }}</span>
            <span data-showcase-status aria-live="polite">{{ $showcase ? 'Loading 3D preview' : 'Product image' }}</span>
        </span>
        <span class="pointer-events-none absolute inset-x-4 bottom-4 flex items-center justify-between gap-3">
            <span class="rounded-sm border border-white/10 bg-black/45 px-3 py-2 text-xs font-semibold text-white backdrop-blur">{{ $product->name }}</span>
            <span class="grid h-11 w-11 place-items-center border border-tech-400 bg-tech-600 text-white transition group-hover:bg-tech-500" aria-hidden="true">
                <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
            </span>
        </span>
    </a>
    @if($showcase)
        <button
            type="button"
            data-showcase-rotation-toggle
            aria-pressed="false"
            aria-label="Play or pause lamp rotation"
            disabled
            class="absolute right-4 top-11 z-10 min-h-11 rounded-sm border border-slate-600 bg-white/85 px-3 py-2 font-mono text-[10px] font-semibold uppercase tracking-[.08em] text-slate-800 shadow-sm outline-none backdrop-blur transition hover:bg-white focus-visible:ring-2 focus-visible:ring-tech-500 disabled:cursor-wait disabled:opacity-60"
        >Rotation</button>
    @endif
</div>
