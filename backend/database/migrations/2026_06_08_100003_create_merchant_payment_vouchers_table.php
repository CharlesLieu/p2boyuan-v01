<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_vouchers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('voucher_no', 40)->unique();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('payout_record_id')->nullable()->constrained('payout_records')->nullOnDelete();
            $table->string('related_business_no', 80)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 40)->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('payee_name', 120);
            $table->string('payee_account_masked', 120);
            $table->string('payer_name', 120)->nullable();
            $table->json('voucher_file');
            $table->text('remark')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_vouchers');
    }
};
