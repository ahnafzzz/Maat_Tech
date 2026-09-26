<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductShowcaseRegistry;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(ProductShowcaseRegistry $showcaseRegistry)
    {
        $featuredProducts = Product::published()->where('is_featured', true)->take(3)->get();
        $categories = Category::withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('name')->take(4)->get();
        $featuredShowcases = $featuredProducts->mapWithKeys(
            fn (Product $product): array => [$product->id => $showcaseRegistry->forProduct($product)]
        );
        $heroProduct = $featuredProducts->first(
            fn (Product $product): bool => $featuredShowcases->get($product->id) !== null
        );
        foreach ($showcaseRegistry->productSlugs() as $slug) {
            if ($heroProduct) {
                break;
            }
            $heroProduct = Product::published()
                ->where('is_featured', true)
                ->where('slug', $slug)
                ->first();
        }
        $heroProduct ??= $featuredProducts->first();
        $heroShowcase = $heroProduct ? $showcaseRegistry->forProduct($heroProduct) : null;

        return view('home', compact('categories', 'featuredProducts', 'heroProduct', 'heroShowcase'));
    }

    public function products(Request $request)
    {
        $query = Product::published()->with('category');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($builder) use ($request) {
                $builder->where('slug', $request->string('category')->toString());
            });
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        match ($request->input('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'stock_desc' => $query->orderByDesc('stock'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('name')->get();

        return view('products', compact('products', 'categories'));
    }

    public function show(string $slug)
    {
        $product = Product::published()
            ->with(['category', 'reviews' => fn ($query) => $query->approved()->latest()])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedProducts = Product::published()
            ->with('category')
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->take(3)
            ->get();

        return view('product', compact('product', 'relatedProducts'));
    }
}
