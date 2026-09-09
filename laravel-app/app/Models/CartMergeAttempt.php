<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartMergeAttempt extends Model
{
    protected $fillable = ['user_id', 'merge_key', 'fingerprint', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
