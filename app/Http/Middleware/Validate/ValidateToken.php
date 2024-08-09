<?php

namespace App\Http\Middleware\Validate;

use Closure;

class ValidateToken
{
    public function handle($request, Closure $next)
    {
        if (!$request->header('token')) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token')
            ], 403);
        } else {
            if ($request->header('token') != env('API_KEY')) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.token_unauth')
                ], 401);
            }
            
            return $next($request);
        }
    }
}
