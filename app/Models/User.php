<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'phone_number', 'is_super_user', 'enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_user' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function repartidor(): HasOne
    {
        return $this->hasOne(Repartidor::class);
    }

    public function customersCreated(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function visitsCreated(): HasMany
    {
        return $this->hasMany(Visit::class, 'created_by');
    }

    public function salesCreated(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function accountEntriesCreated(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class, 'created_by');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
