<?php

namespace App\Services;

use App\Http\Requests\ProductWriteRequest;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductWriteService
{
    private const CATALOG_FIELDS = [
        'name', 'category_id', 'slug', 'sku', 'price', 'compare_at_price', 'discount_amount',
        'stock', 'status', 'is_featured', 'description', 'seo_title', 'seo_description',
    ];

    public function __construct(private readonly ProductMediaStorage $mediaStorage) {}

    public function create(ProductWriteRequest $request): array
    {
        $validated = $request->validated();
        $newPaths = [];

        try {
            $product = DB::transaction(function () use ($request, $validated, &$newPaths): Product {
                $attributes = $this->normalizedAttributes(null, Arr::only($validated, self::CATALOG_FIELDS));
                $product = Product::create([
                    ...$attributes,
                    'specs' => [],
                    'images' => [],
                ]);

                [$images, $video] = $this->storeUploads($request, $product, $newPaths);
                if ($images !== [] || $video !== null) {
                    $product->forceFill([
                        'images' => $images,
                        'image' => $images[0] ?? null,
                        'video_path' => $video,
                    ])->save();
                }

                return $product->fresh();
            });
        } catch (Throwable $exception) {
            $this->cleanup($newPaths, false);

            throw $exception;
        }

        return ['product' => $product, 'cleanup_failed' => false];
    }

    public function update(ProductWriteRequest $request, Product $product): array
    {
        $validated = $request->validated();
        $this->validateMediaPlan($product, $request);
        $newPaths = [];

        try {
            [$newImages, $newVideo] = $this->storeUploads($request, $product, $newPaths);
            [$updated, $obsoletePaths] = DB::transaction(function () use ($request, $validated, $product, $newImages, $newVideo): array {
                $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $this->validateMediaPlan($locked, $request);

                $existingImages = collect($locked->images ?? [])->filter(fn ($path) => is_string($path) && $path !== '')->values();
                $removedImages = collect($request->input('remove_images', []));
                $images = $existingImages->diff($removedImages)->concat($newImages)->values()->all();
                $imagesChanged = $removedImages->isNotEmpty() || $newImages !== [];
                $oldPrimaryImage = $locked->image;
                $primaryImage = $imagesChanged ? ($images[0] ?? null) : $oldPrimaryImage;
                $oldVideo = $locked->video_path;
                $video = $newVideo ?? ($request->boolean('remove_video') ? null : $oldVideo);
                $attributes = $this->normalizedAttributes($locked, Arr::only($validated, self::CATALOG_FIELDS));

                $locked->forceFill([
                    ...$attributes,
                    'images' => $images,
                    'image' => $primaryImage,
                    'video_path' => $video,
                ])->save();

                $obsolete = $existingImages->diff($images)->values()->all();
                if ($imagesChanged && $oldPrimaryImage && $oldPrimaryImage !== $primaryImage && ! $existingImages->contains($oldPrimaryImage)) {
                    $obsolete[] = $oldPrimaryImage;
                }
                if ($oldVideo && $oldVideo !== $video) {
                    $obsolete[] = $oldVideo;
                }

                return [$locked->fresh(), $obsolete];
            }, 3);
        } catch (Throwable $exception) {
            $this->cleanup($newPaths, false);

            throw $exception;
        }

        return [
            'product' => $updated,
            'cleanup_failed' => ! $this->cleanup($obsoletePaths, true),
        ];
    }

    public function delete(Product $product): array
    {
        $paths = DB::transaction(function () use ($product): array {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $paths = $this->productMediaPaths($locked);
            $locked->delete();

            return $paths;
        }, 3);

        return ['cleanup_failed' => ! $this->cleanup($paths, true)];
    }

    private function normalizedAttributes(?Product $product, array $input): array
    {
        $base = $product ? Arr::only($product->getAttributes(), self::CATALOG_FIELDS) : [
            'slug' => $this->generatedSlug((string) $input['name']),
            'sku' => 'ML-'.Str::upper(Str::random(12)),
            'compare_at_price' => null,
            'discount_amount' => '0.00',
            'status' => 'active',
            'is_featured' => false,
            'description' => null,
            'seo_title' => null,
            'seo_description' => null,
        ];
        $attributes = array_replace($base, $input);
        $attributes['discount_amount'] = $attributes['discount_amount'] ?? 0;

        $price = $this->moneyToMinor($attributes['price']);
        $compareAt = $attributes['compare_at_price'] === null ? null : $this->moneyToMinor($attributes['compare_at_price']);
        $discount = $this->moneyToMinor($attributes['discount_amount']);
        if ($compareAt !== null && $compareAt < $price) {
            throw ValidationException::withMessages(['compare_at_price' => 'The compare-at price must be greater than or equal to the product price.']);
        }
        if ($discount > $price) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount amount must not exceed the product price.']);
        }

        $attributes['price'] = $this->minorToMoney($price);
        $attributes['compare_at_price'] = $compareAt === null ? null : $this->minorToMoney($compareAt);
        $attributes['discount_amount'] = $this->minorToMoney($discount);

        return $attributes;
    }

    private function validateMediaPlan(Product $product, ProductWriteRequest $request): void
    {
        $existing = collect($product->images ?? [])->filter(fn ($path) => is_string($path) && $path !== '')->values();
        $removals = collect($request->input('remove_images', []));
        if ($removals->diff($existing)->isNotEmpty()) {
            throw ValidationException::withMessages(['remove_images' => 'Only media currently associated with this product may be removed.']);
        }
        if ($existing->diff($removals)->count() + count($request->file('images', [])) > 10) {
            throw ValidationException::withMessages(['images' => 'A product can have a maximum of 10 photos. Remove existing photos before uploading more.']);
        }
    }

    private function storeUploads(ProductWriteRequest $request, Product $product, array &$newPaths): array
    {
        $images = [];
        foreach ($request->file('images', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $this->mediaStorage->store($file, "products/{$product->id}/images");
            $images[] = $path;
            $newPaths[] = $path;
        }

        $video = null;
        if ($request->hasFile('video')) {
            $video = $this->mediaStorage->store($request->file('video'), "products/{$product->id}/video");
            $newPaths[] = $video;
        }

        return [$images, $video];
    }

    private function cleanup(array $paths, bool $onlyUnreferenced): bool
    {
        $successful = true;
        foreach (array_values(array_unique(array_filter($paths, fn ($path) => is_string($path) && $path !== ''))) as $path) {
            if ($onlyUnreferenced && $this->isReferenced($path)) {
                continue;
            }

            try {
                $successful = $this->mediaStorage->delete($path) && $successful;
            } catch (Throwable) {
                $successful = false;
            }
        }

        return $successful;
    }

    private function isReferenced(string $path): bool
    {
        return Product::query()->get(['image', 'images', 'video_path'])->contains(function (Product $product) use ($path): bool {
            return $product->image === $path
                || $product->video_path === $path
                || in_array($path, $product->images ?? [], true);
        });
    }

    private function productMediaPaths(Product $product): array
    {
        return array_values(array_unique(array_filter([
            $product->image,
            ...($product->images ?? []),
            $product->video_path,
        ], fn ($path) => is_string($path) && $path !== '')));
    }

    private function generatedSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';

        return Str::limit($base, 235, '').'-'.Str::lower(Str::random(12));
    }

    private function moneyToMinor(mixed $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches)) {
            throw ValidationException::withMessages(['price' => 'Monetary values must use no more than two decimal places.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function minorToMoney(int $minor): string
    {
        return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }
}
