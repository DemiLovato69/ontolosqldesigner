<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Diagram;
use App\Repositories\LibraryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LibraryService
{
    private const VERSION_KEY = 'library.v';
    private const CACHE_TTL   = 3600;

    public function __construct(private readonly LibraryRepository $libraryRepository) {}

    /**
     * Return featured and paginated library diagrams, served from cache.
     *
     * @return array{featured: Collection<int, Diagram>, diagrams: LengthAwarePaginator}
     */
    public function getLibraryData(): array
    {
        $v = (int) Cache::get(self::VERSION_KEY, 0);

        $featured = Cache::remember("library.featured.{$v}", self::CACHE_TTL, function () {
            return $this->libraryRepository->getFeatured();
        });

        $page     = max(1, (int) request('page', 1));
        $diagrams = Cache::remember("library.diagrams.{$v}.{$page}", self::CACHE_TTL, function () {
            return $this->libraryRepository->getSharedByUsersPaginated()->withPath(url('/library'));
        });

        return compact('featured', 'diagrams');
    }

    /**
     * Bump the cache version key to invalidate all cached library pages.
     */
    public function invalidate(): void
    {
        Cache::increment(self::VERSION_KEY);
    }
}
