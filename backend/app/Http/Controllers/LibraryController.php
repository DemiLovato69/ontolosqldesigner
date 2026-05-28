<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LibraryService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class LibraryController extends Controller
{
    public function __construct(private readonly LibraryService $libraryService) {}

    public function index(): View|Factory
    {
        ['featured' => $featured, 'diagrams' => $diagrams] = $this->libraryService->getLibraryData();

        return view('library', compact('featured', 'diagrams'));
    }
}
