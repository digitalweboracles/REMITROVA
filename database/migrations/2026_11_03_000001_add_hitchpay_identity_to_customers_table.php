<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HitchPay (live) refuses to generate an account until the customer has an
 * approved BVN verification. We store HitchPay's own customer uuid (so a
 * customer is never enrolled twice) and the BVN itself, encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('hitchpay_customer_uuid')->nullable()->after('address_postal_code');
            $table->text('bvn')->nullable()->after('hitchpay_customer_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['hitchpay_customer_uuid', 'bvn']);
        });
    }
};
