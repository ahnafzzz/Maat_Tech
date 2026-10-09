<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): string
    {
        if (str_starts_with((string) $this->image_path, 'storefront/') && Storage::disk('public')->exists($this->image_path)) {
            return Storage::disk('public')->url($this->image_path);
        }

        return asset(ltrim((string) $this->image_path, '/'));
    }

    public function href(): string
    {
        $url = (string) ($this->link_url ?: '/products');

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
