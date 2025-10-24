<?php

namespace App\Providers;

use App\Repositories\ConsumableRepository;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use App\Services\ConsumableService;
use App\Services\SupplierService;
use App\Services\WarehouseService;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;

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
        $this->app->scoped(\App\Support\ClientContext::class, function () {
            return new \App\Support\ClientContext();
        });
        $this->app->singleton(\App\Services\WB\ProductPriceSyncService::class, function () {
            return new \App\Services\WB\ProductPriceSyncService(
                new \GuzzleHttp\Client(['timeout' => 60])
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
