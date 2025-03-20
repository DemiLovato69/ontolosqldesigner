<?php

namespace App\Providers;

use App\Repositories\DiagramRepository;
use App\Repositories\DiagramRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DiagramRepositoryInterface::class, DiagramRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
