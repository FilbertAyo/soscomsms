<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'contact_group_id',
        'name',
        'phone',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
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
     * @return BelongsTo<ContactGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id');
    }

    /**
     * @return array{id: int, name: string, phone: string, status: string, groupId: int|null, groupName: string|null, createdAt: string|null}
     */
    public function toApiArray(): array
    {
        $this->loadMissing('group');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'groupId' => $this->contact_group_id,
            'groupName' => $this->group?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
