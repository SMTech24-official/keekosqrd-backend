<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\JwtHelper;
use Exception;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));

            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JwtHelper::decodeToken($token);

            // Attach user info to the request for further use
            $request->merge(['user_id' => $decoded->user_id, 'is_admin' => $decoded->is_admin]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}
