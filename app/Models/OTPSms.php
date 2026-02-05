<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTPSms extends Model
{
    use HasFactory;

    protected $table = 'o_t_p_sms';

    protected $fillable = [
        'mobile_number',
        'otp',
        'is_expired',
        'status',
        'validity_time',
        'type',
    ];

    protected $casts = [
        'is_expired' => 'boolean',
        'status' => 'boolean',
        'validity_time' => 'integer',
    ];
}
