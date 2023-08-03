<?php

namespace App\Providers;

use App\Repositories\ConsumableRepository;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConsumableRepositoryInterface::class, ConsumableRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
