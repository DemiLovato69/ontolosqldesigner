<?php

namespace App\Services;

use App\Repositories\LibraryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class LibraryService
{
    private const CACHE_KEY = 'library.page';
    private const CACHE_TTL = 3600;

    public function __construct(private readonly LibraryRepository $libraryRepository) {}

    /** @return array{featured: Collection, diagrams: Collection} */
    public function getLibraryData(): array
    {
        [$featured, $diagrams] = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return [
                $this->libraryRepository->getFeatured(),
                $this->libraryRepository->getSharedByUsers(),
            ];
        });

        return compact('featured', 'diagrams');
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
