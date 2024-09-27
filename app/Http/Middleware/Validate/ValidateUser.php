<?php

namespace App\Http\Middleware\Validate;

use Closure;
use App\Models\Login;
use Symfony\Component\HttpFoundation\Response;

class ValidateUser
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

        $user = Login::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.invalid_token')
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->role != 'User') {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.invalid_user_role')
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

}
 