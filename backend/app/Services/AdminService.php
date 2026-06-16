<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

class AdminService
{
    /** @return array{users: LengthAwarePaginator<int, User>, totalUsers: int, registrationsByDay: array<string, int>, activityByDay: array<string, int>, returningUsers: int, retentionRate: float} */
    public function getDashboardData(string $sort = 'registered'): array
    {
        $tz = 'Europe/Moscow';
        $cutoff = now($tz)->subDays(59)->startOfDay()->utc();
        $dateExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'DATE(created_at)'
            : "DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Moscow')";

        $rows = DB::table('users')
            ->selectRaw("{$dateExpression} as day, COUNT(*) as count")
            ->where('created_at', '>=', $cutoff)
            ->groupByRaw($dateExpression)
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($i = 59; $i >= 0; $i--) {
            $date = now($tz)->subDays($i)->format('Y-m-d');
            $days[$date] = $rows->has($date) ? (int) $rows[$date]->count : 0;
        }

        $activityRows = DB::table('diagram_changelog')
            ->selectRaw("{$dateExpression} as day, COUNT(DISTINCT user_id) as count")
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $cutoff)
            ->groupByRaw($dateExpression)
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $activityByDay = [];
        for ($i = 59; $i >= 0; $i--) {
            $date = now($tz)->subDays($i)->format('Y-m-d');
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

        $totalUsers = User::count();

        $returningUsers = DB::table(
            DB::table('diagram_changelog')
                ->selectRaw('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw("COUNT(DISTINCT {$dateExpression}) >= 2"),
            'sub'
        )->count();

        $retentionRate = $totalUsers > 0 ? round($returningUsers / $totalUsers * 100, 1) : 0;

        return [
            'users' => $usersQuery->paginate(20)->withQueryString(),
            'totalUsers' => $totalUsers,
            'activityByDay' => $activityByDay,
            'registrationsByDay' => $days,
            'returningUsers' => $returningUsers,
            'retentionRate' => $retentionRate,
        ];
    }

    /** @return Collection<int, Diagram> */
    public function getLibraryDiagrams(): Collection
    {
        return Diagram::with('user')
            ->library()
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function featureDiagram(Diagram $diagram, ?string $url): void
    {
        $diagram->featured = true;
        $diagram->featured_url = $url;
        $diagram->save();
    }

    public function unfeatureDiagram(Diagram $diagram): void
    {
        $diagram->featured = false;
        $diagram->featured_url = null;
        $diagram->save();
    }

    public function impersonate(User $user): string
    {
        return $user->createToken('admin-impersonate')->plainTextToken;
    }

    public function createUser(string $email, #[SensitiveParameter] string $password): User
    {
        return User::create([
            'email' => $email,
            'password' => $password,
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    public function updateUserRole(User $user, string $role): bool
    {
        if (! in_array($role, ['user', 'admin'], true)) {
            return false;
        }

        if ($user->role === 'admin' && $role !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return false;
        }

        $user->role = $role;
        $user->save();

        return true;
    }

    public function verifyUser(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        return $user->markEmailAsVerified();
    }

    /**
     * Delete a user along with all their diagrams and tokens.
     */
    public function deleteUser(User $user): void
    {
        $user->diagrams()->delete();
        $user->tokens()->delete();
        $user->delete();
    }
}
