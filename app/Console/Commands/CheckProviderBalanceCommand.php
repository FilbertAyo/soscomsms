<?php

namespace App\Console\Commands;

use App\Contracts\SmsGatewayInterface;
use App\Models\Provider;
use App\Models\User;
use App\Notifications\ProviderLowBalanceNotification;
use Illuminate\Console\Command;

class CheckProviderBalanceCommand extends Command
{
    protected $signature = 'sms:check-provider-balance';

    protected $description = 'Check the upstream SMS provider balance and email admins when it is low.';

    public function handle(SmsGatewayInterface $gateway): int
    {
        $snapshot = $gateway->checkBalance();
        $provider = Provider::current()->fresh() ?? Provider::current();
        $low = $snapshot->smsBalance < $provider->low_balance_threshold;

        if (! $low) {
            if ($provider->low_balance_alerted_at !== null) {
                $provider->forceFill(['low_balance_alerted_at' => null])->save();
            }

            $this->info("Provider balance is {$snapshot->smsBalance} SMS.");

            return self::SUCCESS;
        }

        $cooldownHours = (int) config('sms.production.low_balance_alert_hours', 12);
        $recent = $provider->low_balance_alerted_at !== null
            && $provider->low_balance_alerted_at->gt(now()->subHours($cooldownHours));

        if ($recent) {
            $this->warn("Provider balance is {$snapshot->smsBalance} SMS. Alert already sent.");

            return self::SUCCESS;
        }

        User::query()
            ->where('role', 'admin')
            ->each(function (User $admin) use ($provider, $snapshot): void {
                $admin->notify(new ProviderLowBalanceNotification($provider, $snapshot->smsBalance));
            });

        $provider->forceFill(['low_balance_alerted_at' => now()])->save();
        $this->warn("Provider balance is {$snapshot->smsBalance} SMS. Admins were notified.");

        return self::SUCCESS;
    }
}
