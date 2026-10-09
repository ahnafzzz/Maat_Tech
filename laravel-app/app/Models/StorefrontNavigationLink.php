<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StorefrontNavigationLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'open_new_tab' => 'boolean'];
    }

    public function scopeVisible(Builder $query, string $location): Builder
    {
        return $query->where('location', $location)->where('is_active', true)
            ->orderBy('column')->orderBy('sort_order')->orderBy('id');
    }

    public function href(): string
    {
        return str_starts_with($this->url, '/') ? url($this->url) : $this->url;
    }
}
