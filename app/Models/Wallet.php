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

    /**
     * Atomically increments the balance and returns the new value.
     * Uses a row-level lock + a raw increment (not read-modify-write in
     * PHP) so two concurrent credits can never race and clobber each
     * other. Always call this from inside a DB transaction alongside
     * the LedgerEntry write that justifies the credit.
     */
    public function creditAtomically(string $amount): void
    {
        DB::table('wallets')
            ->where('id', $this->id)
            ->lockForUpdate()
            ->increment('balance', $amount);

        $this->refresh();
    }

    /**
     * Atomically debits the balance, throwing if it would go negative.
     * Same locking approach as creditAtomically().
     */
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
