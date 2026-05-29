<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = Auth::user();
        if ($user) {
            $user->timestamps = false;
            $user->last_seen_at = now();
            $user->save();
        }

        return $response;
    }
}
