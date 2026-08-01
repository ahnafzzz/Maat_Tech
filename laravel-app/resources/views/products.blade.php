@extends('layouts.storefront')
@section('title', 'Catalog')
@section('meta_description', 'Browse precision lighting systems and accessories from MAAT TECHNOLOGIE BD.')
@section('content')
<main class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
    <header class="mb-10 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.22em] text-tech-400">STORE_FRONT / CATALOG</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Precision Units</h1>
        </div>
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-xs font-mono text-tech-400 hover:text-tech-300"><i data-lucide="arrow-left" class="h-4 w-4"></i>RETURN_HOME</a>
    </header>

    <section class="mb-8 grid gap-4 rounded-xl border border-cyber-border bg-cyber-panel/60 p-4 lg:grid-cols-[1.4fr,1fr,1fr,1fr,auto] lg:items-end">
        <form method="GET" action="{{ route('products') }}" class="contents">
            <label class="text-xs font-mono text-slate-400">SEARCH
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, SKU, description" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] px-3 py-3 text-sm text-white outline-none focus:border-tech-500">
            </label>
            <label class="text-xs font-mono text-slate-400">CATEGORY
                <select name="category" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] px-3 py-3 text-sm text-white outline-none focus:border-tech-500">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-mono text-slate-400">SORT
                <select name="sort" class="mt-2 w-full rounded-lg border border-cyber-border bg-[#090d14] px-3 py-3 text-sm text-white outline-none focus:border-tech-500">
                    <option value="latest" @selected(request('sort') === 'latest')>Latest</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                    <option value="stock_desc" @selected(request('sort') === 'stock_desc')>Stock: High to Low</option>
                </select>
            </label>
            <label class="flex items-center gap-2 rounded-lg border border-cyber-border bg-[#090d14] px-3 py-3 text-sm text-slate-300"><input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock')) class="accent-teal-500"> In stock only</label>
            <div class="flex gap-2">
                <button class="rounded-lg border border-tech-400 bg-tech-600 px-4 py-3 text-xs font-mono text-white transition hover:bg-tech-500">APPLY</button>
                <a href="{{ route('products') }}" class="rounded-lg border border-cyber-border px-4 py-3 text-xs font-mono text-slate-300 transition hover:border-tech-600 hover:text-tech-300">RESET</a>
            </div>
        </form>
    </section>

    <div class="mb-6 text-sm text-slate-500">{{ $products->total() }} results found.</div>

    @if($products->isEmpty())
        <section class="glass-panel grid min-h-60 place-items-center p-8 text-center">
            <div>
                <i data-lucide="search-x" class="mx-auto h-10 w-10 text-tech-400"></i>
                <p class="mt-4 text-lg font-semibold text-white">No products matched your filters.</p>
                <p class="mt-2 text-sm text-slate-500">Try clearing a filter, changing the search term, or browsing all categories.</p>
            </div>
        </section>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($products as $product)
                <article class="glass-panel group overflow-hidden transition hover:-translate-y-1 hover:border-tech-600">
                    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-[4/3] overflow-hidden bg-[#0d121b]">
                        @if(!empty($product->images))
                            <img src="{{ asset('storage/' . $product->images[0]) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                        @elseif($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                        @else
                            <div class="grid h-full place-items-center text-tech-400"><i data-lucide="image-off" class="h-16 w-16" stroke-width="1"></i></div>
                        @endif
                        <span class="absolute left-3 top-3 rounded-md border border-tech-800 bg-tech-950/90 px-2 py-1 font-mono text-[10px] text-tech-300">{{ $product->category->name ?? 'CATALOG' }}</span>
                        @if($product->has_discount)
                            <span class="absolute right-3 top-3 rounded-md border border-rose-800 bg-rose-950/90 px-2 py-1 font-mono text-[10px] text-rose-200">SALE</span>
                        @endif
                    </a>
                    <div class="p-5">
                        <div class="flex justify-between gap-3">
                            <p class="font-mono text-[10px] text-tech-400">{{ $product->sku ?: 'UNIT-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</p>
                            <p class="font-mono text-[10px] text-slate-500">STOCK: {{ $product->stock }}</p>
                        </div>
                        <a href="{{ route('products.show', $product->slug) }}" class="mt-2 block text-lg font-semibold text-white hover:text-tech-300">{{ $product->name }}</a>
                        <p class="mt-2 min-h-10 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($product->description, 90) }}</p>
                        <div class="mt-4 flex items-center justify-between border-t border-cyber-border pt-4">
                            <div>
                                @if($product->has_discount)
                                    <div class="font-mono text-xs text-slate-500 line-through">BDT {{ number_format($product->price) }}</div>
                                @endif
                                <strong class="font-mono text-lg text-white">BDT {{ number_format($product->final_price) }}</strong>
                            </div>
                            <form method="POST" action="{{ route('cart.add', $product) }}">
                                @csrf
                                <button title="Add {{ $product->name }} to cart" class="grid h-9 w-9 place-items-center rounded-lg border border-tech-600 text-tech-300 transition hover:bg-tech-600 hover:text-white"><i data-lucide="plus" class="h-4 w-4"></i></button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</main>
@endsection
