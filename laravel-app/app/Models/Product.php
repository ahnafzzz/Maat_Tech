<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'sku', 'description', 'price', 'compare_at_price', 'discount_amount', 'stock', 'specs', 'image', 'images', 'video_path', 'variants', 'is_featured', 'status', 'seo_title', 'seo_description'];

    protected $casts = [
        'specs' => 'array',
        'images' => 'array',
        'variants' => 'array',
        'is_featured' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getFinalPriceAttribute(): float
    {
        return max(0, (float) $this->price - (float) $this->discount_amount);
    }

    public function getHasDiscountAttribute(): bool
    {
        return (float) $this->discount_amount > 0;
    }
}
