@extends('layouts.storefront')
@section('title', $product->seo_title ?: $product->name)
@section('meta_description', $product->seo_description ?: \Illuminate\Support\Str::limit($product->description, 150))
@section('content')
<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <a href="{{ route('products') }}" class="inline-flex items-center gap-2 text-xs font-mono text-tech-400 hover:text-tech-300"><i data-lucide="arrow-left" class="h-4 w-4"></i>RETURN_TO_CATALOG</a>

    @php
        $gallery = collect($product->images ?? [])->filter()->values();
        if ($gallery->isEmpty() && $product->image) {
            $gallery = collect([$product->image]);
        }
    @endphp

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <section class="glass-panel p-6 sm:p-8">
            @if ($gallery->isNotEmpty())
                <img id="product-main-image" src="{{ asset('storage/' . $gallery->first()) }}" alt="{{ $product->name }}" class="aspect-[16/9] w-full rounded-lg border border-cyber-border bg-[#0c1119] object-cover transition duration-300 hover:scale-[1.01]">
            @else
                <div class="grid aspect-[16/9] place-items-center rounded-lg border border-cyber-border bg-[#0c1119] text-tech-400"><i data-lucide="image-off" class="h-28 w-28" stroke-width="1"></i></div>
            @endif

            @if ($product->video_path)
                <video controls class="mt-4 w-full rounded-lg border border-cyber-border" src="{{ asset('storage/' . $product->video_path) }}"></video>
            @endif

            @if ($gallery->count() > 1)
                <div class="mt-4 grid grid-cols-4 gap-2">
                    @foreach ($gallery as $image)
                        <button type="button" class="product-thumb overflow-hidden rounded-lg border border-cyber-border bg-[#0c1119]" data-image="{{ asset('storage/' . $image) }}">
                            <img src="{{ asset('storage/' . $image) }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif

            <p class="mt-7 font-mono text-xs text-tech-400">{{ $product->category->name ?? 'CATALOG' }} // {{ $product->sku ?: 'UNIT-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p>
            <h1 class="mt-3 text-3xl font-bold text-white">{{ $product->name }}</h1>
            <p class="mt-4 leading-7 text-slate-400">{{ $product->description }}</p>

            <div class="mt-7 grid gap-6 lg:grid-cols-[1fr,1fr]">
                <div class="rounded-lg border border-cyber-border bg-[#0d121b] p-5">
                    <h2 class="font-mono text-xs text-tech-300">SYSTEM_SPECIFICATION</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        @forelse ($product->specs ?? [] as $key => $value)
                            <div class="border-t border-cyber-border pt-2"><dt class="font-mono text-[10px] uppercase text-slate-500">{{ $key }}</dt><dd class="mt-1 text-sm text-slate-200">{{ $value }}</dd></div>
                        @empty
                            <div class="text-sm text-slate-500">Technical specifications will be updated after final product media and documentation upload.</div>
                        @endforelse
                    </dl>
                </div>
                <div class="rounded-lg border border-cyber-border bg-[#0d121b] p-5">
                    <h2 class="font-mono text-xs text-tech-300">VERIFIED_BUYER_REVIEWS</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($product->reviews as $review)
                            <div class="border-b border-cyber-border pb-3 last:border-b-0 last:pb-0">
                                <p class="text-sm font-semibold text-white">{{ $review->author_name ?? 'Verified buyer' }}</p>
                                <p class="mt-1 text-xs text-tech-300">Rating: {{ $review->rating }}/5</p>
                                <p class="mt-2 text-sm text-slate-400">{{ $review->comment }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Customer reviews will appear here once verified orders begin submitting feedback.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <aside class="glass-panel h-fit p-6">
            <p class="font-mono text-xs text-slate-500">UNIT_PRICE</p>
            @if ($product->has_discount)
                <p class="mt-2 font-mono text-sm text-slate-500 line-through">BDT {{ number_format($product->price) }}</p>
                <p class="mt-1 font-mono text-3xl font-bold text-tech-300">BDT {{ number_format($product->final_price) }}</p>
                <p class="mt-2 inline-block rounded-md border border-rose-800 bg-rose-950/40 px-2 py-1 font-mono text-[10px] text-rose-200">SAVE BDT {{ number_format($product->discount_amount) }}</p>
            @else
                <p class="mt-2 font-mono text-3xl font-bold text-tech-300">BDT {{ number_format($product->final_price) }}</p>
            @endif
            <p class="mt-3 text-sm text-slate-400">Stock status: <span class="text-tech-300">{{ $product->stock > 0 ? ($product->stock <= 3 ? 'Only ' . $product->stock . ' left' : $product->stock . ' available') : 'Out of stock' }}</span></p>
            <div class="mt-4 rounded-lg border border-cyber-border bg-[#0d121b] p-4 text-xs leading-6 text-slate-400">
                <p>Bulk pricing guide:</p>
                <p>2-4 units: 5% discount</p>
                <p>5-9 units: 9% discount</p>
                <p>10+ units: custom project quotation</p>
            </div>

            <form method="POST" action="{{ route('cart.add', $product) }}" class="mt-7">
                @csrf
                <label class="block font-mono text-[10px] text-slate-500">QUANTITY<input type="number" name="quantity" min="1" max="{{ max(1, $product->stock) }}" value="1" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] px-3 py-3 text-sm text-white outline-none focus:border-tech-500"></label>
                <button class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-tech-400 bg-tech-600 px-4 py-3 text-sm font-mono text-white hover:bg-tech-500"><i data-lucide="shopping-cart" class="h-4 w-4"></i>ADD_TO_CART</button>
            </form>
            <form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="mt-3">@csrf<button class="flex w-full items-center justify-center gap-2 rounded-lg border border-cyber-border px-4 py-3 text-sm font-mono text-slate-300 hover:border-tech-600 hover:text-tech-300"><i data-lucide="heart" class="h-4 w-4"></i>WISHLIST</button></form>
            <a href="https://wa.me/8801601934752?text={{ urlencode('I want to order ' . $product->name) }}" target="_blank" rel="noreferrer" class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-700 bg-emerald-950/40 px-4 py-3 text-sm font-mono text-emerald-300 hover:bg-emerald-900/50"><i data-lucide="message-circle" class="h-4 w-4"></i>ORDER_ON_WHATSAPP</a>
            <p class="mt-5 border-t border-cyber-border pt-4 text-xs leading-5 text-slate-500">Cash on Delivery available. Pathao delivery confirmation follows checkout and phone verification.</p>
        </aside>
    </div>

    @if ($relatedProducts->isNotEmpty())
        <section class="mt-12">
            <div class="mb-6">
                <p class="font-mono text-xs tracking-[.22em] text-tech-400">RELATED_UNITS</p>
                <h2 class="mt-2 text-2xl font-bold text-white">You may also need</h2>
            </div>
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($relatedProducts as $related)
                    <article class="glass-panel overflow-hidden transition hover:-translate-y-1 hover:border-tech-600">
                        <a href="{{ route('products.show', $related->slug) }}" class="block aspect-[4/3] overflow-hidden bg-[#0d121b]">
                            @if(!empty($related->images))
                                <img src="{{ asset('storage/' . $related->images[0]) }}" alt="{{ $related->name }}" class="h-full w-full object-cover">
                            @elseif($related->image)
                                <img src="{{ asset('storage/' . $related->image) }}" alt="{{ $related->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="grid h-full place-items-center text-tech-400"><i data-lucide="image-off" class="h-16 w-16"></i></div>
                            @endif
                        </a>
                        <div class="p-5">
                            <p class="font-mono text-[10px] text-tech-400">{{ $related->sku ?: 'UNIT-' . str_pad($related->id, 4, '0', STR_PAD_LEFT) }}</p>
                            <a href="{{ route('products.show', $related->slug) }}" class="mt-2 block font-semibold text-white hover:text-tech-300">{{ $related->name }}</a>
                            <p class="mt-3 font-mono text-sm text-tech-300">BDT {{ number_format($related->final_price) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.product-thumb').forEach(function (button) {
        button.addEventListener('click', function () {
            var main = document.getElementById('product-main-image');
            if (!main) {
                return;
            }
            main.src = button.dataset.image;
        });
    });
</script>
@endpush
