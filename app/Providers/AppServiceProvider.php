<?php

namespace App\Providers;

use App\Repositories\ConsumableRepository;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use App\Services\ConsumableService;
use App\Services\SupplierService;
use App\Services\WarehouseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConsumableRepositoryInterface::class, ConsumableRepository::class);
        $this->app->singleton(WarehouseService::class);
        $this->app->singleton(ConsumableService::class);
        $this->app->singleton(SupplierService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
