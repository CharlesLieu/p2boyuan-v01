<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_code', 32)->unique();
            $table->string('name', 100);
            $table->string('phone', 40)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('task_status', 30)->default('AVAILABLE')->index();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agents');
    }
};
