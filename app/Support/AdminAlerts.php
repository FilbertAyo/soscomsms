<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Admin\TechnicalIssueNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

final class AdminAlerts
{
    public const TECHNICAL_COOLDOWN_MINUTES = 60;

    public static function notify(Notification $notification): void
    {
        $admins = self::admins();
        if ($admins->isEmpty()) {
            return;
        }

        NotificationFacade::send($admins, $notification);
    }

    /**
     * Sent synchronously so the alert still goes out when the queue itself is failing.
     */
    public static function technical(string $key, string $title, string $details): void
    {
        if (! Cache::add('admin-alert:'.$key, true, now()->addMinutes(self::TECHNICAL_COOLDOWN_MINUTES))) {
            return;
        }

        try {
            $admins = self::admins();
            if ($admins->isEmpty()) {
                return;
            }

            NotificationFacade::sendNow($admins, new TechnicalIssueNotification($title, $details));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function admins(): Collection
    {
        return User::query()->where('role', 'admin')->get();
    }
}
