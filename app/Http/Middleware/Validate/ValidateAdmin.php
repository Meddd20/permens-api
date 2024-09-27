<?php

namespace App\Http\Middleware\Validate;

use App\Models\Login;
use Closure;
use Symfony\Component\HttpFoundation\Response;

class ValidateAdmin
{
    public function handle($request, Closure $next)
    {
        $token = $request->header('userToken');
        if (!$token) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.token_missing')
            ], Response::HTTP_UNAUTHORIZED);
        }

        $admin = Login::where('token', $token)->first();

        if (!$admin) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.invalid_token')
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($admin->role != 'Admin') {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.invalid_user_role')
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
