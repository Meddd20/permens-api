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
            if ($request->header('token') != "yghMCYkYmtX6YcHdw8lyL2WpQh1IVCiEBIuqOt3r2XTKZNgnuRzYA1XxteNN") {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.token_unauth')
                ], 401);
            }
            
            return $next($request);
        }
    }
}
