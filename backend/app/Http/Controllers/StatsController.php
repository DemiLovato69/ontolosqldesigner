<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $users = Cache::remember('stats:users', 3600, fn () => User::count());
        $diagrams = Cache::remember('stats:diagrams', 3600, fn () => Diagram::count());
        $online = User::where('last_seen_at', '>=', now()->subMinutes(5))->count();

        return response()->json(compact('users', 'diagrams', 'online'));
    }
}
