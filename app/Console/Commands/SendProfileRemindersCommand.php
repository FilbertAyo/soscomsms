<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Notifications\CompleteProfileReminderNotification;
use Illuminate\Console\Command;

class SendProfileRemindersCommand extends Command
{
    protected $signature = 'customers:remind-incomplete-profiles';

    protected $description = 'Email a friendly reminder to customers who have not completed their business profile.';

    /**
     * Days after sign-up for each reminder, and the minimum days since the previous one.
     * The gap keeps older sign-ups from getting all three reminders on consecutive days.
     */
    private const DAYS_AFTER_SIGNUP = [1, 3, 7];

    private const DAYS_SINCE_PREVIOUS = [0, 2, 4];

    public function handle(): int
    {
        $sent = 0;

        Customer::query()
            ->with('user')
            ->where('status', CustomerStatus::PendingProfile)
            ->where('profile_reminders_sent', '<', CompleteProfileReminderNotification::TOTAL)
            ->whereHas('user', fn ($query) => $query->where('role', 'customer'))
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$sent): void {
                foreach ($customers as $customer) {
                    if ($this->isDue($customer)) {
                        $this->remind($customer);
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} profile reminders.");

        return self::SUCCESS;
    }

    private function isDue(Customer $customer): bool
    {
        $step = $customer->profile_reminders_sent;

        if ($customer->created_at === null || $customer->created_at->gt(now()->subDays(self::DAYS_AFTER_SIGNUP[$step]))) {
            return false;
        }

        return $customer->profile_reminded_at === null
            || $customer->profile_reminded_at->lte(now()->subDays(self::DAYS_SINCE_PREVIOUS[$step]));
    }

    private function remind(Customer $customer): void
    {
        $step = $customer->profile_reminders_sent + 1;

        $customer->forceFill([
            'profile_reminders_sent' => $step,
            'profile_reminded_at' => now(),
        ])->save();

        $customer->user?->notify(new CompleteProfileReminderNotification($step, $customer->sms_balance));
    }
}
