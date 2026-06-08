<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaAndDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_v01_enums_match_documented_values(): void
    {
        $this->assertSame([
            'STORE',
            'SALES',
            'AUDITOR',
            'CASHIER',
            'SUPER_ADMIN',
        ], array_column(UserRole::cases(), 'value'));

        $this->assertSame([
            'DRAFT',
            'PENDING_ASSIGNMENT',
            'ASSIGNED',
            'INSPECTION_IN_PROGRESS',
            'PENDING_REVIEW',
            'NEEDS_SUPPLEMENT',
            'REJECTED',
            'PENDING_PAYOUT',
            'PAID',
            'COMPLETED',
        ], array_column(ApplicationStatus::cases(), 'value'));
    }

    public function test_v01_schema_contains_required_tables_columns_and_indexes(): void
    {
        foreach ([
            'stores',
            'merchant_onboarding_applications',
            'merchant_payment_vouchers',
            'sales_agents',
            'applications',
            'inspection_tasks',
            'review_records',
            'payout_records',
            'attachments',
            'status_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} table is missing");
        }

        foreach ([
            'username',
            'display_name',
            'role',
            'store_id',
            'sales_agent_id',
            'status',
            'last_login_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), "users.{$column} is missing");
        }

        foreach ([
            'onboarding_status',
            'payment_method',
            'payment_account',
            'payment_account_name',
            'payment_bank_or_channel',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('stores', $column), "stores.{$column} is missing");
        }

        foreach ([
            'store_id',
            'applicant_name',
            'applicant_phone',
            'merchant_name',
            'payment_account',
            'id_card_front_file',
            'id_card_back_file',
            'qualification_file',
            'status',
            'reviewer_user_id',
            'reject_reason',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('merchant_onboarding_applications', $column), "merchant_onboarding_applications.{$column} is missing");
        }

        foreach ([
            'voucher_no',
            'store_id',
            'related_business_no',
            'amount',
            'status',
            'paid_at',
            'payee_name',
            'payee_account_masked',
            'voucher_file',
            'void_reason',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('merchant_payment_vouchers', $column), "merchant_payment_vouchers.{$column} is missing");
        }

        foreach ([
            'application_no',
            'source_type',
            'store_id',
            'created_by_user_id',
            'current_owner_role',
            'current_owner_user_id',
            'status',
            'customer_name',
            'customer_phone',
            'id_type',
            'id_number',
            'customer_address',
            'brand',
            'model',
            'color',
            'capacity',
            'imei',
            'device_condition',
            'sale_price',
            'loan_amount',
            'periods',
            'remark',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('applications', $column), "applications.{$column} is missing");
        }

        $indexes = collect(DB::select("PRAGMA index_list('applications')"))->pluck('name')->all();

        $this->assertContains('applications_application_no_unique', $indexes);
        $this->assertContains('applications_status_created_at_index', $indexes);
        $this->assertContains('applications_store_id_status_index', $indexes);

        $this->assertContains(
            'inspection_tasks_sales_agent_id_status_index',
            collect(DB::select("PRAGMA index_list('inspection_tasks')"))->pluck('name')->all(),
        );
        $this->assertContains(
            'review_records_application_id_created_at_index',
            collect(DB::select("PRAGMA index_list('review_records')"))->pluck('name')->all(),
        );
        $this->assertContains(
            'payout_records_status_created_at_index',
            collect(DB::select("PRAGMA index_list('payout_records')"))->pluck('name')->all(),
        );
        $this->assertContains(
            'attachments_application_id_module_index',
            collect(DB::select("PRAGMA index_list('attachments')"))->pluck('name')->all(),
        );
        $this->assertContains(
            'status_logs_application_id_created_at_index',
            collect(DB::select("PRAGMA index_list('status_logs')"))->pluck('name')->all(),
        );
    }

    public function test_user_assignment_foreign_keys_exist_and_reject_invalid_references(): void
    {
        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('users')"));

        $this->assertTrue(
            $foreignKeys->contains(fn ($key) => $key->from === 'store_id' && $key->table === 'stores' && $key->on_delete === 'SET NULL'),
            'users.store_id should reference stores.id with ON DELETE SET NULL',
        );
        $this->assertTrue(
            $foreignKeys->contains(fn ($key) => $key->from === 'sales_agent_id' && $key->table === 'sales_agents' && $key->on_delete === 'SET NULL'),
            'users.sales_agent_id should reference sales_agents.id with ON DELETE SET NULL',
        );

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'username' => 'invalid-store-user',
            'display_name' => 'Invalid Store User',
            'role' => UserRole::STORE->value,
            'store_id' => '00000000-0000-0000-0000-000000000000',
            'status' => 'ACTIVE',
            'password' => 'demo-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_demo_seeder_creates_v01_demo_workflow_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, DB::table('stores')->count());
        $this->assertSame(2, DB::table('merchant_onboarding_applications')->count());
        $this->assertSame(2, DB::table('sales_agents')->count());
        $this->assertSame(7, DB::table('users')->count());

        foreach (['admin001', 'audit001', 'cashier001', 'sales001', 'sales002', 'store001', 'store002'] as $username) {
            $this->assertDatabaseHas('users', ['username' => $username, 'status' => 'ACTIVE']);
        }

        $this->assertSame(8, DB::table('applications')->count());

        foreach ([
            'PENDING_ASSIGNMENT',
            'ASSIGNED',
            'INSPECTION_IN_PROGRESS',
            'PENDING_REVIEW',
            'NEEDS_SUPPLEMENT',
            'REJECTED',
            'PENDING_PAYOUT',
            'PAID',
        ] as $status) {
            $this->assertDatabaseHas('applications', ['status' => $status]);
        }

        $this->assertGreaterThanOrEqual(3, DB::table('inspection_tasks')->count());
        $this->assertGreaterThanOrEqual(4, DB::table('review_records')->count());
        $this->assertGreaterThanOrEqual(2, DB::table('payout_records')->count());
        $this->assertGreaterThanOrEqual(3, DB::table('merchant_payment_vouchers')->count());
        $this->assertGreaterThanOrEqual(8, DB::table('attachments')->count());
        $this->assertGreaterThanOrEqual(8, DB::table('status_logs')->count());

        $this->assertDatabaseHas('stores', ['store_code' => 'STORE-001', 'onboarding_status' => 'APPROVED']);
        $this->assertDatabaseHas('stores', ['store_code' => 'STORE-002', 'onboarding_status' => 'REJECTED']);
        $this->assertDatabaseHas('merchant_payment_vouchers', ['status' => 'PAID']);
        $this->assertDatabaseHas('merchant_payment_vouchers', ['status' => 'PENDING_CONFIRMATION']);
        $this->assertDatabaseHas('merchant_payment_vouchers', ['status' => 'VOIDED']);
    }
}
