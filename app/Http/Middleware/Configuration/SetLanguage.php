<?php

namespace App\Http\Middleware\Configuration;

use Closure;

class SetLanguage
{
    public function handle($request, Closure $next)
    {
        if (!$request->header('lang')) {
            \App::setLocale('en');
            return $next($request);
        } else {
            if ($request->header('lang')=='en' || $request->header('lang')=='id') {
                \App::setLocale($request->header('lang'));
                
                return $next($request);
            } else {
                return response()->json([
                    "status" => "failed",
                    "message" => "Language Not Available"
                ], 403);
            }
        }
    }
}
