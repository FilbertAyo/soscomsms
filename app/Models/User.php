<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasCompleteProfile(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $this->loadMissing('customer');

        return $this->customer !== null
            && $this->customer->status !== CustomerStatus::PendingProfile;
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, customer: array{id: int, businessName: string|null, phone: string|null, jobTitle: string|null, status: string, smsBalance: int}|null}
     */
    public function toAuthArray(): array
    {
        $this->loadMissing('customer');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'customer' => $this->customer?->toAuthArray(),
        ];
    }
}
