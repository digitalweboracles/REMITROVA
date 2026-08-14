<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersistentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'wallet_id', 'provider', 'account_reference',
        'account_identifier', 'bank_name', 'status', 'raw_create_response',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'raw_create_response' => 'array',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
