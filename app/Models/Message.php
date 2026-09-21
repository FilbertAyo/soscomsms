<?php

namespace App\Models;

use App\Enums\MessageEncoding;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'body',
        'encoding',
        'character_count',
        'segment_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encoding' => MessageEncoding::class,
            'character_count' => 'integer',
            'segment_count' => 'integer',
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
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return array{id: int, body: string, encoding: string, characterCount: int, segmentCount: int}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'encoding' => $this->encoding->value,
            'characterCount' => $this->character_count,
            'segmentCount' => $this->segment_count,
        ];
    }
}
