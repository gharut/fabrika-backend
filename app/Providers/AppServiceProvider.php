<?php

namespace App\Providers;

use App\Repositories\ConsumableRepository;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;
use App\Services\ConsumableService;
use App\Services\SupplierService;
use App\Services\WarehouseService;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;
use App\Services\WbServiceRep;

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
        $this->app->singleton(WbServiceRep::class, function () {
            return new WbServiceRep(
                new Client([
                    'base_uri' => config('wb.base_url'),
                    'timeout'  => 30,
                ]),
                config('wb.token'),
                config('wb.rate')
            );
        });
        $this->app->scoped(\App\Support\ClientContext::class, function () {
            return new \App\Support\ClientContext();
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
