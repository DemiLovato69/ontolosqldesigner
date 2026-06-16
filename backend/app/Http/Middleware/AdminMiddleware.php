<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            return redirect('/admin/login');
        }

        return $next($request);
    }
}
