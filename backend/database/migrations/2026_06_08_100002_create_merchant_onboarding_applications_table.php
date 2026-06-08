<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_onboarding_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('applicant_name', 100);
            $table->string('applicant_phone', 40);
            $table->string('applicant_id_number', 80);
            $table->string('merchant_name', 100);
            $table->string('merchant_address', 255);
            $table->string('contact_name', 100);
            $table->string('contact_phone', 40);
            $table->string('payment_method', 40);
            $table->string('payment_account', 120);
            $table->string('payment_account_name', 120);
            $table->string('payment_bank_or_channel', 120)->nullable();
            $table->json('id_card_front_file');
            $table->json('id_card_back_file');
            $table->json('qualification_file');
            $table->string('status', 40)->index();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_onboarding_applications');
    }
};
