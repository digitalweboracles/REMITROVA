<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'country',
        'sender_formal_name', 'sender_gender', 'sender_occupation',
        'sender_age', 'sender_address',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function wallet(string $currency): ?Wallet
    {
        return $this->wallets->firstWhere('currency', $currency);
    }

    public function persistentAccounts()
    {
        return $this->hasMany(PersistentAccount::class);
    }

    /**
     * Whether this customer has filled in every field Paga's IMTO
     * requirements need before we can send them through Deposit To
     * Bank / Money Transfer. Enforce this at the point of initiating a
     * transfer, not just at signup, since it's the single source of
     * truth for "can this customer legally send money right now."
     */
    public function hasCompletedImtoKyc(): bool
    {
        return filled($this->sender_formal_name)
            && filled($this->sender_gender)
            && filled($this->sender_occupation)
            && filled($this->sender_age)
            && filled($this->sender_address);
    }
}
