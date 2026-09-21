<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        User $actor,
        AuditAction $action,
        ?Model $subject = null,
        ?Customer $customer = null,
        array $payload = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actor->id,
            'customer_id' => $customer?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
