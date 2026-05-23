<?php

namespace App\Repositories;

use App\Models\Diagram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LibraryRepository
{
    public function getFeatured(): Collection
    {
        return Diagram::where('featured', true)
            ->whereNotNull('share_access')
            ->orderByDesc('updated_at')
            ->get(['name', 'share_token', 'featured_url', 'updated_at']);
    }

    public function getSharedByUsers(): Collection
    {
        return Diagram::where('library', true)
            ->where('featured', false)
            ->whereNotNull('share_access')
            ->orderByDesc('updated_at')
            ->get(['name', 'share_token', 'updated_at']);
    }

    public function getSharedByUsersPaginated(int $perPage = 24): LengthAwarePaginator
    {
        return Diagram::where('library', true)
            ->where('featured', false)
            ->whereNotNull('share_access')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['name', 'share_token', 'updated_at']);
    }
}
