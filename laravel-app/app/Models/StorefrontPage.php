<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'items' => 'array',
            'is_visible' => 'boolean',
        ];
    }
}
