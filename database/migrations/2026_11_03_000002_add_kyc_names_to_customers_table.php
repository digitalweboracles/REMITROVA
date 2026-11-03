<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name on a BVN must match what we send HitchPay, and it can differ from
 * the display name on the account, so the KYC step stores it separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['kyc_first_name', 'kyc_last_name'] as $column) {
            if (!Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['kyc_first_name', 'kyc_last_name'] as $column) {
            if (Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
