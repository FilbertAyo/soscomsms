<?php

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'sms:dispatch-scheduled';

    protected $description = 'Queue due SOSCOM scheduled campaigns. Never sends scheduledDate to the SMS gateway.';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $count = $dispatcher->releaseDueScheduled();
        $this->info("Released {$count} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
