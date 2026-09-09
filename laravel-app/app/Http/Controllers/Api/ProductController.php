<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductWriteRequest;
use App\Models\Product;
use App\Services\ProductWriteService;

class ProductController extends Controller
{
    public function __construct(private readonly ProductWriteService $productWriteService) {}

    public function index()
    {
        return Product::published()->with('category')->latest()->get();
    }

    public function store(ProductWriteRequest $request)
    {
        $result = $this->productWriteService->create($request);

        return response()->json([
            ...$result['product']->toArray(),
            'media_cleanup_pending' => $result['cleanup_failed'],
        ], 201);
    }

    public function show(string $id)
    {
        return Product::published()->with('category')->findOrFail($id);
    }

    public function update(ProductWriteRequest $request, Product $product)
    {
        $result = $this->productWriteService->update($request, $product);

        return response()->json([
            ...$result['product']->toArray(),
            'media_cleanup_pending' => $result['cleanup_failed'],
        ]);
    }

    public function destroy(Product $product)
    {
        $result = $this->productWriteService->delete($product);

        return response()->json([
            'message' => 'Deleted',
            'media_cleanup_pending' => $result['cleanup_failed'],
        ]);
    }
}
