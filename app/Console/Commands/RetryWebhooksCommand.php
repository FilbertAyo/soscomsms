<?php

namespace App\Console\Commands;

use App\Services\WebhookDispatcher;
use Illuminate\Console\Command;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Dispatch due customer webhook retries';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $count = $dispatcher->retryDue();
        $this->info("Queued {$count} webhook retries.");

        return self::SUCCESS;
    }
}
