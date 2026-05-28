<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Diagram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LibraryRepository
{
    /**
     * Return all featured, shared diagrams ordered by most recently updated.
     */
    public function getFeatured(): Collection
    {
        return Diagram::where('featured', true)
            ->whereNotNull('share_access')
            ->orderByDesc('updated_at')
            ->get(['name', 'share_token', 'featured_url', 'updated_at']);
    }

    /**
     * Return a paginated list of non-featured, user-shared library diagrams.
     */
    public function getSharedByUsersPaginated(int $perPage = 24): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->paginate($perPage, ['name', 'share_token', 'updated_at']);
    }

    private function baseQuery(): Builder
    {
        return Diagram::where('library', true)
            ->where('featured', false)
            ->whereNotNull('share_access')
            ->orderByDesc('updated_at');
    }
}
