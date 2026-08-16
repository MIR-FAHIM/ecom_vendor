<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'trial_days',
        'max_products',
        'max_orders_per_month',
        'max_staff',
        'max_branches',
        'commission_rate',
        'is_featured',
        'is_popular',
        'status',
        'sort_order',
        'features',
        'metadata',
    ];

    protected $casts = [
        'price' => 'float',
        'trial_days' => 'integer',
        'max_products' => 'integer',
        'max_orders_per_month' => 'integer',
        'max_staff' => 'integer',
        'max_branches' => 'integer',
        'commission_rate' => 'float',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
        'metadata' => 'array',
    ];

    public function subscriptions()
    {
        return $this->hasMany(StoreSubscription::class);
    }
}
