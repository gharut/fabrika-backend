<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PricingStrategy;
use App\Jobs\RunPricingStrategyJob;

class RunActiveStrategiesCommand extends Command
{
    protected $signature = 'app:run-strategies';
    protected $description = 'Запускает активные ценовые стратегии по расписанию';

    public function handle(): int
    {
        $now = now()->format('H:i:s');

        $strategies = PricingStrategy::query()
            ->where('status', 'active')
            ->get();

        foreach ($strategies as $strategy) {
            RunPricingStrategyJob::dispatch($strategy->id);
            $this->info("Стратегия #{$strategy->id} добавлена в очередь на выполнение.");
        }

        return Command::SUCCESS;
    }
}