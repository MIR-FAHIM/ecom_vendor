<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiToken;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next, $scope = null)
    {
        // 1. Read Bearer token
        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return response()->json([
                'message' => 'API token missing'
            ], 401);
        }

        // 2. Hash token
        $tokenHash = hash('sha256', $plainToken);

        // 3. Find token
        $apiToken = ApiToken::with('user')
            ->where('token_hash', $tokenHash)
            ->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$apiToken) {
            return response()->json([
                'message' => 'Invalid or expired API token'
            ], 401);
        }

        // 4. Scope check (optional)
        if ($scope && !in_array($scope, $apiToken->scopes ?? [])) {
            return response()->json([
                'message' => 'Insufficient permission'
            ], 403);
        }

        // 5. Attach user & token manually to request
        $request->attributes->set('api_user', $apiToken->user);
        $request->attributes->set('api_token', $apiToken);

        // 6. Update last used time
        $apiToken->update([
            'last_used_at' => now(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
