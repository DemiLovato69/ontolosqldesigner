<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! session('admin_authenticated')) {
            return redirect('/admin/login');
        }

        return $next($request);
    }
}
