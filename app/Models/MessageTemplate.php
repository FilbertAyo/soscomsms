<?php

namespace App\Models;

use App\Enums\MessageEncoding;
use App\Enums\MessageTemplateType;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'type',
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
            'type' => MessageTemplateType::class,
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
     * @return array{id: int, name: string, type: string, body: string, encoding: string, characterCount: int, segmentCount: int}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'body' => $this->body,
            'encoding' => $this->encoding->value,
            'characterCount' => $this->character_count,
            'segmentCount' => $this->segment_count,
        ];
    }
}
