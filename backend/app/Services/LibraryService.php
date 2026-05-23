<?php

namespace App\Services;

use App\Repositories\LibraryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LibraryService
{
    private const VERSION_KEY = 'library.v';
    private const CACHE_TTL  = 3600;

    public function __construct(private readonly LibraryRepository $libraryRepository) {}

    /** @return array{featured: Collection, diagrams: LengthAwarePaginator} */
    public function getLibraryData(): array
    {
        $v = (int) Cache::get(self::VERSION_KEY, 0);

        $featured = Cache::remember("library.featured.{$v}", self::CACHE_TTL, function () {
            return $this->libraryRepository->getFeatured();
        });

        $page     = max(1, (int) request('page', 1));
        $diagrams = Cache::remember("library.diagrams.{$v}.{$page}", self::CACHE_TTL, function () {
            return $this->libraryRepository->getSharedByUsersPaginated();
        });

        return compact('featured', 'diagrams');
    }

    public function invalidate(): void
    {
        Cache::increment(self::VERSION_KEY);
    }
}
