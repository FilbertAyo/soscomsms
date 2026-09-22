<?php

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'sms:dispatch-scheduled';

    protected $description = 'Queue due SOSCOM scheduled campaigns and resume stuck sending campaigns. Never sends scheduledDate to the SMS gateway.';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $released = $dispatcher->releaseDueScheduled();
        $this->info("Released {$released} scheduled campaign(s).");

        $resumed = $dispatcher->resumeStuckSending();
        $this->info("Resumed {$resumed} stuck sending campaign(s).");

        return self::SUCCESS;
    }
}
