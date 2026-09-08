<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'country',
        'sender_formal_name', 'sender_gender', 'sender_occupation',
        'sender_age', 'sender_address',
        'date_of_birth', 'nationality', 'profile_image_url',
        'address_house_no', 'address_street', 'address_city',
        'address_state', 'address_postal_code',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
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

    public function hasCompletedImtoKyc(): bool
    {
        return filled($this->sender_formal_name)
            && filled($this->sender_gender)
            && filled($this->sender_occupation)
            && filled($this->sender_age)
            && filled($this->sender_address);
    }

    /**
     * HitchPay's customer enrollment requires all of these — a
     * distinct, more detailed set than Paga's IMTO requirement, and
     * not currently collected at signup. Nothing calls this to block
     * signup/login; it exists purely so HitchPayProvider can check
     * before attempting enrollment and refuse honestly (naming
     * exactly what's missing) rather than sending fabricated values.
     */
    public function hasCompletedHitchPayKyc(): bool
    {
        return filled($this->name)
            && filled($this->email)
            && filled($this->phone)
            && filled($this->date_of_birth)
            && filled($this->nationality)
            && filled($this->profile_image_url)
            && filled($this->address_house_no)
            && filled($this->address_street)
            && filled($this->address_city)
            && filled($this->address_state)
            && filled($this->address_postal_code);
    }
}
