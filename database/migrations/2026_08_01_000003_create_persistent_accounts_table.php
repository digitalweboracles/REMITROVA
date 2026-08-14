<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persistent_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete(); // which wallet gets credited on deposit

            $table->string('provider')->default('paga');
            $table->string('account_reference')->unique(); // our reference, sent to Paga on creation
            $table->string('account_identifier')->nullable(); // Paga's returned NUBAN / account number
            $table->string('bank_name')->nullable();

            $table->enum('status', ['pending', 'active', 'deleted', 'failed'])->default('pending');
            $table->json('raw_create_response')->nullable(); // full Paga response, for audit/debugging
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persistent_accounts');
    }
};
