<?php

namespace App\Http\Middleware\Validate;

use Closure;

class ValidateUser
{
    public function handle($request, Closure $next)
    {
        if (!$request->header('user_id')) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.user_id')
            ], 400);
        }

        return $next($request);
    }
}
