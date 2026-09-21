<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'url',
        'secret',
        'events',
        'enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensFor(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events ?? [], true);
    }

    /**
     * @return array{id: int, name: string, url: string, events: list<string>, enabled: bool, createdAt: string|null, updatedAt: string|null}
     */
    public function toApiArray(): array
    {
        /** @var list<string> $events */
        $events = array_values(array_filter(
            $this->events ?? [],
            fn (mixed $event): bool => is_string($event),
        ));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $events,
            'enabled' => $this->enabled,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
