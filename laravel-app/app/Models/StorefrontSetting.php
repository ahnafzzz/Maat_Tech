<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StorefrontSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'featured_product_id' => 'integer',
            'slideshow_enabled' => 'boolean',
            'wishlist_enabled' => 'boolean',
            'cart_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    public function logoUrl(): string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset('images/brand/maat-tech-logo.png');
    }

    public function color(string $attribute, string $fallback): string
    {
        $value = (string) $this->getAttribute($attribute);

        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) === 1 ? $value : $fallback;
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        if (! $this->whatsapp_number) {
            return null;
        }

        $url = 'https://wa.me/'.$this->whatsapp_number;

        return $message ? $url.'?text='.urlencode($message) : $url;
    }
}
