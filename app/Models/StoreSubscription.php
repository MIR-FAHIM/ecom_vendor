<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'subscription_package_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'price',
        'currency',
        'billing_cycle',
        'payment_status',
        'payment_reference',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'price' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Shops::class, 'store_id');
    }

    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }
}
