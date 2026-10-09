@extends('layouts.storefront')
@section('title', 'Wishlist')
@section('content')
<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <header class="mb-8 flex items-end justify-between">
        <div><h1 class="mt-2 text-3xl font-bold text-white">Wishlist</h1></div>
        <a href="{{ route('products') }}" class="inline-flex min-h-11 items-center font-mono text-xs text-tech-400 outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">Browse products</a>
    </header>
    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @forelse($items as $item)
            <article class="glass-panel overflow-hidden">
                <div class="grid aspect-[4/3] place-items-center bg-[#0c1119] text-tech-400">@if($item['available'])<img src="{{ $item['product']->primaryImageUrl() }}" alt="{{ $item['product']->name }}" class="h-full w-full object-contain" loading="lazy">@else<i data-lucide="image-off" class="h-16 w-16" stroke-width="1"></i>@endif</div>
                <div class="p-5">
                    @if($item['available'])
                        <p class="font-mono text-[10px] text-tech-400">{{ $item['product']->category?->name ?? 'CATALOG' }}</p>
                        <h2 class="mt-2 text-lg font-semibold text-white">{{ $item['product']->name }}</h2>
                        <p class="mt-2 min-h-10 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($item['product']->description, 84) }}</p>
                        <div class="mt-4 flex items-center justify-between border-t border-cyber-border pt-4">
                            <strong class="font-mono text-tech-300">BDT {{ number_format($item['product']->final_price) }}</strong>
                            <a href="{{ route('products.show', $item['product']->slug) }}" class="inline-flex min-h-11 items-center font-mono text-xs text-slate-300 outline-none hover:text-tech-300 focus-visible:ring-2 focus-visible:ring-tech-300">View product</a>
                        </div>
                    @else
                        <p class="font-mono text-[10px] text-slate-500">UNAVAILABLE</p>
                        <h2 class="mt-2 text-lg font-semibold text-white">Unavailable item</h2>
                        <p class="mt-2 min-h-10 text-sm text-slate-500">This saved item is no longer published.</p>
                    @endif
                    <form method="POST" action="{{ route('wishlist.toggle', $item['product_id']) }}" class="mt-4 border-t border-cyber-border pt-4">
                        @csrf
                        <button class="min-h-11 px-3 font-mono text-xs text-rose-300 outline-none hover:text-rose-200 focus-visible:ring-2 focus-visible:ring-rose-300">Remove</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="glass-panel p-8 text-center text-slate-400 md:col-span-2 lg:col-span-3">Your wishlist is empty.</div>
        @endforelse
    </div>
</main>
@endsection
