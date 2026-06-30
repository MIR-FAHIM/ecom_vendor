<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlinePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_group_id',
        'order_ids',
        'user_id',
        'gateway',
        'merchant_transaction_id',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'gateway_fee',
        'gateway_response',
        'initiated_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'order_ids' => 'array',
        'gateway_response' => 'array',
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
