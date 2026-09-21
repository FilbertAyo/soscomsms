<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignChunkJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public int $campaignId,
        public array $recipientIds,
    ) {}

    public function handle(CampaignDispatcher $dispatcher): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign === null || $campaign->status === CampaignStatus::Cancelled) {
            return;
        }

        $dispatcher->dispatchChunk($campaign, $this->recipientIds);
    }
}
