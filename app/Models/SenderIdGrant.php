<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderIdGrant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sender_id_id',
        'customer_id',
    ];

    /**
     * @return BelongsTo<SenderId, $this>
     */
    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
