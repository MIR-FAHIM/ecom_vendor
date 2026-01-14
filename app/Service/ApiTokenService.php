<?php

namespace App\Service;

use Illuminate\Support\Str;
use App\Models\User;
use App\Models\ApiToken;

class ApiTokenService
{
    /**
     * Create a new API token for a user.
     * Returns array: ['plain' => string, 'token' => ApiToken]
     */
    public static function create(User $user, array $scopes = [], $days = 30, $name = null)
    {
        $plain = Str::random(64);

        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'scopes' => $scopes,
            'expires_at' => now()->addDays($days),
            'name' => $name,
        ]);

        return ['plain' => $plain, 'token' => $apiToken]; // return ONCE
    }
}