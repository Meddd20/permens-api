<?php

namespace App\Http\Middleware\Validate;

use App\Models\Login;
use Closure;
use Illuminate\Http\Request;

class ValidateAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user_id = $request->header("user_id");
        $user = Login::where('id', $user_id)
            ->where('role', 'Admin')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.user_not_admin')
            ], 403);
        }

        return $next($request);
    }
}
