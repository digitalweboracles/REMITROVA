<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('country', ['PL', 'NG']); // home base

            // IMTO-mandatory sender fields, confirmed required by Paga on
            // every Deposit To Bank / Money Transfer call for our account
            // type. Captured once here at KYC/profile time rather than
            // re-collected per transaction. Nullable at the DB level so a
            // customer can complete signup before finishing KYC, but the
            // application layer must enforce these are filled in before
            // any transfer is allowed to proceed.
            $table->string('sender_formal_name')->nullable();
            $table->enum('sender_gender', ['M', 'F'])->nullable();
            $table->string('sender_occupation')->nullable();
            $table->unsignedTinyInteger('sender_age')->nullable();
            $table->text('sender_address')->nullable();

            $table->timestamp('kyc_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
