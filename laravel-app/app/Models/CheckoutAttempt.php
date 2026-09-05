<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutAttempt extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_identifier',
        'attempt_key',
        'fingerprint',
        'cart_snapshot',
        'order_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
