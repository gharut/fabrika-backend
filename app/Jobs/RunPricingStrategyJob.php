<?php

namespace App\Jobs;

use App\Services\PricingStrategyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunPricingStrategyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $strategyId) {}

    public function handle(PricingStrategyService $service): void
    {
        $service->run($this->strategyId, config('wb.token'));
    }
}