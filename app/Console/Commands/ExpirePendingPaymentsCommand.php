<?php

namespace App\Console\Commands;

use App\Notifications\Admin\PaymentsExpiredNotification;
use App\Services\PaymentService;
use App\Support\AdminAlerts;
use Illuminate\Console\Command;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Expire pending SMS purchases whose phone prompt was not approved';

    public function handle(PaymentService $payments): int
    {
        $expired = $payments->expireDue();

        if ($expired->isNotEmpty()) {
            AdminAlerts::notify(new PaymentsExpiredNotification($expired));
        }

        $this->info("Expired {$expired->count()} pending payments.");

        return self::SUCCESS;
    }
}
