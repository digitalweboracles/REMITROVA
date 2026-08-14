<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider', 'event_type', 'provider_reference', 'headers',
        'payload', 'hash_verified', 'processed_at', 'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'hash_verified' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
