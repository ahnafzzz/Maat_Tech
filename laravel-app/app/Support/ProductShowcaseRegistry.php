<?php

namespace App\Support;

use App\Models\Product;

class ProductShowcaseRegistry
{
    /**
     * @return list<string>
     */
    public function productSlugs(): array
    {
        $associations = config('product-showcases.products', []);

        if (! is_array($associations)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($associations),
            fn (mixed $slug): bool => is_string($slug) && $slug !== '' && $this->valid($associations[$slug])
        ));
    }

    /**
     * @return array{model_id: string, manifest: string, poster: string}|null
     */
    public function forProduct(Product $product): ?array
    {
        $association = config('product-showcases.products.'.$product->slug);

        if (! $this->valid($association)) {
            return null;
        }

        return [
            'model_id' => $association['model_id'],
            'manifest' => $association['manifest'],
            'poster' => $association['poster'],
        ];
    }

    private function valid(mixed $association): bool
    {
        return is_array($association)
            && is_string($association['model_id'] ?? null)
            && $association['model_id'] !== ''
            && is_string($association['manifest'] ?? null)
            && $association['manifest'] !== ''
            && is_string($association['poster'] ?? null)
            && $association['poster'] !== '';
    }
}
