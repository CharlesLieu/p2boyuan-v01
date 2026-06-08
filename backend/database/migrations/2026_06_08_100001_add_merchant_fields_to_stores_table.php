<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('onboarding_status', 40)->default('PENDING_REVIEW')->after('status')->index();
            $table->string('payment_method', 40)->nullable()->after('onboarding_status');
            $table->string('payment_account', 120)->nullable()->after('payment_method');
            $table->string('payment_account_name', 120)->nullable()->after('payment_account');
            $table->string('payment_bank_or_channel', 120)->nullable()->after('payment_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn([
                'onboarding_status',
                'payment_method',
                'payment_account',
                'payment_account_name',
                'payment_bank_or_channel',
            ]);
        });
    }
};
