<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('currency', ['PLN', 'NGN']);

            // DECIMAL, never FLOAT/DOUBLE — floating point arithmetic on
            // money is how you get off-by-a-fraction-of-a-kobo bugs that
            // compound into real reconciliation problems. 4 decimal
            // places gives headroom beyond either currency's 2 official
            // decimal places, useful if we ever need to store a
            // fractional FX remainder internally.
            $table->decimal('balance', 20, 4)->default(0);

            $table->timestamps();

            // One wallet per currency per customer.
            $table->unique(['customer_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
