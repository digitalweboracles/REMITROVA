<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields HitchPay's customer enrollment requires that we
 * don't currently collect at signup: date of birth, nationality,
 * a profile photo URL, and a fully structured address (house number,
 * street, city, state, postal code) — separate from the existing
 * free-text sender_address field, which Paga's IMTO requirement uses
 * and which isn't structured the way HitchPay needs.
 *
 * All nullable: existing customers (and anyone signing up through the
 * current, unchanged signup form) simply won't have these until a
 * real KYC step collects them — HitchPayProvider checks for their
 * presence and refuses honestly rather than fabricating values when
 * they're missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('sender_address');
            $table->string('nationality', 2)->nullable()->after('date_of_birth'); // ISO 3166-1 alpha-2
            $table->string('profile_image_url')->nullable()->after('nationality');
            $table->string('address_house_no')->nullable()->after('profile_image_url');
            $table->string('address_street')->nullable()->after('address_house_no');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_state')->nullable()->after('address_city');
            $table->string('address_postal_code')->nullable()->after('address_state');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'nationality', 'profile_image_url',
                'address_house_no', 'address_street', 'address_city',
                'address_state', 'address_postal_code',
            ]);
        });
    }
};
