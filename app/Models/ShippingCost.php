<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_cost',
        'is_shop_wise',
        'is_distance_wise',
        'is_product_wise',
        'per_shop_cost',
        'status',
    ];

    protected $casts = [
        'shipping_cost' => 'float',
        'is_shop_wise' => 'boolean',
        'is_distance_wise' => 'boolean',
        'is_product_wise' => 'boolean',
        'per_shop_cost' => 'float',
    ];
}
