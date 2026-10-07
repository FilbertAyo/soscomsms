<?php

use App\Console\Commands\BackupDatabaseCommand;
use App\Console\Commands\CheckProviderBalanceCommand;
use App\Console\Commands\DispatchScheduledCampaignsCommand;
use App\Console\Commands\DrainQueueCommand;
use App\Console\Commands\ExpirePendingPaymentsCommand;
use App\Console\Commands\PollDeliveryReportsCommand;
use App\Console\Commands\RetryWebhooksCommand;
use App\Console\Commands\SendProfileRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DrainQueueCommand::class, ['--max-time=50'])
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command(DispatchScheduledCampaignsCommand::class)
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command(PollDeliveryReportsCommand::class)
    ->everyMinute();

Schedule::command(RetryWebhooksCommand::class)
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command(ExpirePendingPaymentsCommand::class)
    ->hourly()
    ->withoutOverlapping(30);

Schedule::command(CheckProviderBalanceCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

Schedule::command(BackupDatabaseCommand::class)
    ->dailyAt('02:15')
    ->withoutOverlapping(60);

Schedule::command(SendProfileRemindersCommand::class)
    ->dailyAt('10:00')
    ->withoutOverlapping(30);
