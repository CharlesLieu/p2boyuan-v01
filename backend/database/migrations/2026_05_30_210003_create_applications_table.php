<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_no', 32)->unique();
            $table->string('source_type', 32);
            $table->foreignUuid('store_id')->constrained('stores');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('current_owner_role', 32)->nullable();
            $table->foreignId('current_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40);
            $table->string('customer_name', 100);
            $table->string('customer_phone', 40);
            $table->string('id_type', 30);
            $table->string('id_number', 80);
            $table->string('customer_address', 255);
            $table->string('brand', 50);
            $table->string('model', 80);
            $table->string('color', 50)->nullable();
            $table->string('capacity', 50)->nullable();
            $table->string('imei', 80)->nullable();
            $table->string('device_condition', 255)->nullable();
            $table->decimal('sale_price', 12, 2);
            $table->decimal('loan_amount', 12, 2);
            $table->integer('periods');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
