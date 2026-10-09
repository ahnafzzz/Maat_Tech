<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'sku', 'description', 'price', 'compare_at_price', 'discount_amount', 'stock', 'specs', 'image', 'images', 'video_path', 'variants', 'is_featured', 'status', 'seo_title', 'seo_description'];

    protected $casts = [
        'specs' => 'array',
        'images' => 'array',
        'variants' => 'array',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isPublished(): bool
    {
        return $this->status === 'active';
    }

    public function getFinalPriceAttribute(): float
    {
        return $this->finalPriceMinor() / 100;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discountAmountMinor() > 0;
    }

    public function priceMinor(): int
    {
        return $this->moneyToMinor((string) $this->price);
    }

    public function discountAmountMinor(): int
    {
        return $this->moneyToMinor((string) ($this->discount_amount ?? '0'));
    }

    public function finalPriceMinor(): int
    {
        return max(0, $this->priceMinor() - $this->discountAmountMinor());
    }

    public function discountPercent(): int
    {
        $price = $this->priceMinor();

        return $price === 0 ? 0 : (int) round(($this->discountAmountMinor() * 100) / $price);
    }

    /** @return list<array{key:string,label:string,available:bool,stock:int}> */
    public function purchasableVariants(): array
    {
        return collect($this->variants ?? [])->filter(fn ($variant) => is_array($variant))
            ->map(function (array $variant): ?array {
                $key = strtolower(trim((string) ($variant['key'] ?? '')));
                $label = trim((string) ($variant['label'] ?? ''));
                if ($key === '' || $label === '' || preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $key) !== 1) {
                    return null;
                }

                $stock = max(0, (int) ($variant['stock'] ?? 0));

                return ['key' => $key, 'label' => $label, 'available' => (bool) ($variant['available'] ?? false), 'stock' => $stock];
            })->filter()->unique('key')->values()->all();
    }

    public function purchasableVariant(?string $key): ?array
    {
        $variants = $this->purchasableVariants();
        if ($variants === []) {
            return $key === null || $key === ''
                ? ['key' => '', 'label' => null, 'available' => $this->stock > 0, 'stock' => $this->stock]
                : null;
        }

        return collect($variants)->firstWhere('key', strtolower(trim((string) $key)));
    }

    /** @return list<string> */
    public function galleryImageUrls(): array
    {
        $paths = collect($this->images ?? [])
            ->push($this->image)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->filter(fn (string $path) => Storage::disk('public')->exists($path))
            ->take(7)
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values();

        if ($paths->isEmpty() && $this->slug === 'series-x-articulated-lamp') {
            $paths->push(asset('images/products/led-swing-arm-desk-lamp/black-workspace.jpg'));
        }

        return $paths->all();
    }

    public function primaryImageUrl(): string
    {
        return $this->galleryImageUrls()[0] ?? asset('images/brand/maat-tech-mark.png');
    }

    public function videoUrl(): ?string
    {
        return is_string($this->video_path) && $this->video_path !== '' && Storage::disk('public')->exists($this->video_path)
            ? Storage::disk('public')->url($this->video_path)
            : null;
    }

    private function moneyToMinor(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
