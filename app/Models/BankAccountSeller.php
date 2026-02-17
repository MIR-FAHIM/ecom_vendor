<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccountSeller extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'user_id',
        'account_name',
        'account_no',
        'type',
        'address',
        'route',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
