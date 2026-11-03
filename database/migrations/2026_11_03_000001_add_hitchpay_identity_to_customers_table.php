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
        // Guarded: some environments already have one or both columns.
        if (!Schema::hasColumn('customers', 'hitchpay_customer_uuid')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('hitchpay_customer_uuid')->nullable();
            });
        }

        if (!Schema::hasColumn('customers', 'bvn')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->text('bvn')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['hitchpay_customer_uuid', 'bvn'] as $column) {
            if (Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
