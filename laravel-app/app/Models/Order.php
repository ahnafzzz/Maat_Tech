<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'order_number', 'status', 'payment_method', 'payment_status', 'shipping_method', 'tracking_number', 'subtotal', 'shipping_fee', 'total', 'customer_name', 'customer_phone', 'district', 'address', 'customer_note', 'shipping_address', 'placed_at'];

    protected $casts = [
        'shipping_address' => 'array',
        'placed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
