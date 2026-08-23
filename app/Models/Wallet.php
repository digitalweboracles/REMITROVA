<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'currency', 'balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function creditAtomically(string $amount): void
    {
        DB::table('wallets')
            ->where('id', $this->id)
            ->lockForUpdate()
            ->increment('balance', $amount);

        $this->refresh();
    }

    public function debitAtomically(string $amount): void
    {
        $locked = DB::table('wallets')->where('id', $this->id)->lockForUpdate()->first();

        if (bccomp($locked->balance, $amount, 4) < 0) {
            throw new \RuntimeException("Insufficient balance on wallet {$this->id}: has {$locked->balance}, needs {$amount}.");
        }

        DB::table('wallets')->where('id', $this->id)->decrement('balance', $amount);
        $this->refresh();
    }
}
