<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Expire pending SMS purchase requests that were not confirmed';

    public function handle(PaymentService $payments): int
    {
        $count = $payments->expireDue();
        $this->info("Expired {$count} pending payments.");

        return self::SUCCESS;
    }
}
