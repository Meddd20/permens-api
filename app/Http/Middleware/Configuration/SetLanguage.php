<?php

namespace App\Http\Middleware\Configuration;

use Closure;

class SetLanguage
{
    public function handle($request, Closure $next)
    {
        if (!$request->input('lang')) {
            \App::setLocale('en');
            return $next($request);
        } else {
            if ($request->input('lang')=='en' || $request->input('lang')=='id') {
                \App::setLocale($request->input('lang'));
                
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
