<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');       // "paga"
            $table->string('event_type');     // "persistent_account_deposit", etc.
            $table->string('provider_reference')->nullable(); // Paga's transactionReference, if present

            $table->json('headers');          // raw incoming headers, incl. x-paga-hash / x-paga-hash-parameters
            $table->json('payload');          // raw decoded body, exactly as received
            $table->boolean('hash_verified'); // result of PagaHasher::verifyCallback()

            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamps();

            // A Paga transactionReference should only ever be processed
            // once. If the same reference arrives twice (Paga retry,
            // network duplicate, or an attacker replaying a captured
            // payload), this constraint stops it at the database level —
            // the second insert simply fails, which the webhook
            // controller treats as "already handled, return 200 anyway"
            // rather than an error.
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
