<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::where('is_featured', true)->take(3)->get();
        $categories = Category::withCount('products')->orderBy('name')->take(4)->get();

        return view('home', compact('categories', 'featuredProducts'));
    }

    public function products(Request $request)
    {
        $query = Product::with('category')->where('status', 'active');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%');
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
        $categories = Category::withCount('products')->orderBy('name')->get();

        return view('products', compact('products', 'categories'));
    }

    public function show(string $slug)
    {
        $product = Product::with(['category', 'reviews'])->where('slug', $slug)->firstOrFail();

        $relatedProducts = Product::with('category')
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->take(3)
            ->get();

        return view('product', compact('product', 'relatedProducts'));
    }
}
