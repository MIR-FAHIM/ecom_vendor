<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginSuccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'api_token_id',
        'login_type',
        'platform',
        'identifier',
        'token_name',
        'name',
        'email',
        'phone',
        'user_type',
        'ip_address',
        'user_agent',
        'logged_in_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'api_token_id' => 'integer',
        'logged_in_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiToken()
    {
        return $this->belongsTo(ApiToken::class);
    }
}
