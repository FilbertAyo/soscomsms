<?php

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'sms:dispatch-scheduled';

    protected $description = 'Queue due SOSCOM scheduled campaigns, cancel stuck attempted sends, and continue never-attempted chunks. Never sends scheduledDate to the SMS gateway.';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $released = $dispatcher->releaseDueScheduled();
        $this->info("Released {$released} scheduled campaign(s).");

        $cancelled = $dispatcher->cancelStuckSending();
        $this->info("Cancelled {$cancelled} stuck sending campaign(s).");

        $resumed = $dispatcher->resumeStuckSending();
        $this->info("Resumed {$resumed} never-attempted campaign chunk(s).");

        return self::SUCCESS;
    }
}
