<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Diagram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LibraryRepository
{
    /**
     * @return Collection<int, Diagram>
     */
    public function getFeatured(): Collection
    {
        return Diagram::featured()
            ->orderByDesc('updated_at')
            ->get(['name', 'share_token', 'featured_url', 'updated_at']);
    }

    /**
     * Return a paginated list of non-featured, user-shared library diagrams.
     *
     * @return LengthAwarePaginator<int, Diagram>
     */
    public function getSharedByUsersPaginated(int $perPage = 24): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->paginate($perPage, ['name', 'share_token', 'updated_at']);
    }

    /** @return Builder<Diagram> */
    private function baseQuery(): Builder
    {
        return Diagram::library()
            ->where('featured', false)
            ->shared()
            ->orderByDesc('updated_at');
    }
}
