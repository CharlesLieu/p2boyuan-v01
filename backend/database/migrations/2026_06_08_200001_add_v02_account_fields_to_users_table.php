<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'password_updated_at')) {
                $table->timestamp('password_updated_at')->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable()->after('password_updated_at');
            }

            if (! Schema::hasColumn('users', 'disabled_reason')) {
                $table->string('disabled_reason', 255)->nullable()->after('disabled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'disabled_reason')) {
                $table->dropColumn('disabled_reason');
            }

            if (Schema::hasColumn('users', 'disabled_at')) {
                $table->dropColumn('disabled_at');
            }

            if (Schema::hasColumn('users', 'password_updated_at')) {
                $table->dropColumn('password_updated_at');
            }
        });
    }
};
