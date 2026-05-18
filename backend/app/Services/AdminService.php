<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(private readonly LibraryService $libraryService) {}

    public function authenticate(string $username, string $password): bool
    {
        return hash_equals('admin', $username)
            && hash_equals((string)config('app.admin_password'), $password);
    }

    /** @return array{users: Collection, libraryDiagrams: Collection, registrationsByDay: array} */
    public function getDashboardData(string $sort = 'registered'): array
    {
        $rows = DB::table('users')
            ->selectRaw("DATE(created_at) as day, COUNT(*) as count")
            ->where('created_at', '>=', now()->subDays(59)->startOfDay())
            ->groupByRaw("DATE(created_at)")
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($i = 59; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[$date] = $rows->has($date) ? (int) $rows[$date]->count : 0;
        }

        $activityRows = DB::table('diagram_changelog')
            ->selectRaw("DATE(created_at) as day, COUNT(DISTINCT user_id) as count")
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(59)->startOfDay())
            ->groupByRaw("DATE(created_at)")
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $activityByDay = [];
        for ($i = 59; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $activityByDay[$date] = $activityRows->has($date) ? (int) $activityRows[$date]->count : 0;
        }

        $usersQuery = User::with('diagrams');

        if ($sort === 'last_action') {
            $usersQuery
                ->leftJoinSub(
                    DB::table('diagram_changelog')
                        ->selectRaw('user_id, MAX(created_at) as last_action_at')
                        ->whereNotNull('user_id')
                        ->groupBy('user_id'),
                    'changelog_agg',
                    'changelog_agg.user_id', '=', 'users.id'
                )
                ->orderByRaw('changelog_agg.last_action_at DESC NULLS LAST')
                ->select('users.*');
        } else {
            $usersQuery->orderBy('created_at', 'desc');
        }

        return [
            'users' => $usersQuery->get(),
            'activityByDay' => $activityByDay,
            'registrationsByDay' => $days,
        ];
    }

    public function getLibraryDiagrams(): \Illuminate\Database\Eloquent\Collection
    {
        return Diagram::with('user')
            ->where('library', true)
            ->whereNotNull('share_access')
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function featureDiagram(Diagram $diagram, string $url): void
    {
        $diagram->featured = true;
        $diagram->featured_url = $url;
        $diagram->save();
        $this->libraryService->invalidate();
    }

    public function unfeatureDiagram(Diagram $diagram): void
    {
        $diagram->featured = false;
        $diagram->featured_url = null;
        $diagram->save();
        $this->libraryService->invalidate();
    }

    public function impersonate(User $user): string
    {
        return $user->createToken('admin-impersonate')->plainTextToken;
    }

    public function deleteUser(User $user): void
    {
        $user->diagrams()->delete();
        $user->tokens()->delete();
        $user->delete();
    }
}
