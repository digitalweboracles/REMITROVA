<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 20, 4); // always positive; direction says which way it moves the balance
            $table->enum('currency', ['PLN', 'NGN']);

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'reversed'])->default('pending');

            // Idempotency: this is what stops a retried webhook or a
            // duplicated queue job from crediting the same deposit twice.
            // Every code path that creates a ledger entry MUST derive
            // this key deterministically from the source event (e.g.
            // "paga_deposit:{transactionReference}") rather than
            // generating a fresh UUID, or the protection is worthless.
            $table->string('idempotency_key')->unique();

            $table->string('provider')->nullable();            // e.g. "paga"
            $table->string('provider_reference')->nullable();  // Paga's transactionReference / referenceNumber
            $table->string('type');                             // e.g. "nuban_deposit", "disbursement", "adjustment"
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();               // sender name, raw amounts pre-fee, etc.

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
