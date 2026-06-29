<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckQuota
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->monthly_quota !== null && $user->used_this_month >= $user->monthly_quota) {
            return response()->json(['error' => 'Monthly quota exceeded'], 429);
        }

        return $next($request);
    }
}
