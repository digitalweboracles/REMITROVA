<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id', 'direction', 'amount', 'currency', 'status',
        'idempotency_key', 'provider', 'provider_reference', 'type',
        'description', 'metadata', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Ledger entries are append-only by design — once written, a
     * completed/failed entry should never be edited, only superseded by
     * a new entry (e.g. a "reversed" direction-flipped entry). This
     * guard makes accidental mutation of a settled entry fail loudly
     * instead of silently corrupting the financial record.
     */
    public function save(array $options = [])
    {
        if ($this->exists && $this->getOriginal('status') === 'completed' && $this->isDirty(['amount', 'direction', 'wallet_id'])) {
            throw new \RuntimeException('Refusing to mutate a completed ledger entry — create a new entry instead.');
        }

        return parent::save($options);
    }
}
