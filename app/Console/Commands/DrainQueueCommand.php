<?php

namespace App\Console\Commands;

use App\Services\ProviderReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DrainQueueCommand extends Command
{
    protected $signature = 'sms:drain-queue {--max-time=50 : Stop the worker after this many seconds}';

    protected $description = 'Process pending database jobs, then mark the queue heartbeat.';

    public function handle(): int
    {
        $maxTime = max(5, (int) $this->option('max-time'));

        $this->call('queue:work', [
            'connection' => 'database',
            '--stop-when-empty' => true,
            '--max-time' => $maxTime,
            '--tries' => 1,
            '--timeout' => min(45, $maxTime),
            '--memory' => 256,
        ]);

        Cache::put(
            ProviderReadinessService::QUEUE_HEARTBEAT_KEY,
            now()->toIso8601String(),
            now()->addMinutes(10),
        );

        $this->info('Queue drained.');

        return self::SUCCESS;
    }
}
