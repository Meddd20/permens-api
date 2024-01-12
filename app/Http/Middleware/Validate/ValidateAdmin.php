<?php

namespace App\Http\Middleware\Validate;

use App\Models\UToken;
use App\Models\Login;
use Closure;

class ValidateAdmin
{
    public function handle($request, Closure $next)
    {
        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

        if (!$user_id) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.user_id')
            ], 400);
        }

        $user = Login::find($user_id);

        if (!$user || ($user->role !== 'Admin')) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.invalid_user_role')
            ], 400);
        }

        return $next($request);
    }
}
