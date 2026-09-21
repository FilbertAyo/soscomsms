<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $campaignId) {}

    public function uniqueId(): string
    {
        return 'campaign-'.$this->campaignId;
    }

    public function handle(CampaignDispatcher $dispatcher): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        if (! in_array($campaign->status, [CampaignStatus::Queued, CampaignStatus::Sending], true)) {
            return;
        }

        $dispatcher->dispatch($campaign);
    }
}
