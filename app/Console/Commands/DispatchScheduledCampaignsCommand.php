<?php

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledCampaignsCommand extends Command
{
    protected $signature = 'sms:dispatch-scheduled';

    protected $description = 'Queue due SOSCOM scheduled campaigns, hold ambiguous attempted sends (admin alert only, no credit change), and continue never-attempted chunks. Never sends scheduledDate to the SMS gateway.';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $released = $dispatcher->releaseDueScheduled();
        $this->info("Released {$released} scheduled campaign(s).");

        $ambiguous = $dispatcher->cancelStuckSending();
        $this->info("Held {$ambiguous} ambiguous send campaign(s) for admin review.");

        $resumed = $dispatcher->resumeStuckSending();
        $this->info("Resumed {$resumed} never-attempted campaign chunk(s).");

        return self::SUCCESS;
    }
}
