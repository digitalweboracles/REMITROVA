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
            $table->decimal('amount', 20, 4);
            $table->enum('currency', ['PLN', 'NGN']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'reversed'])->default('pending');

            $table->string('idempotency_key')->unique();

            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

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
