<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('status_logs')->delete();
            DB::table('payout_records')->delete();
            DB::table('attachments')->delete();
            DB::table('review_records')->delete();
            DB::table('inspection_tasks')->delete();
            DB::table('applications')->delete();
            DB::table('users')->delete();
            DB::table('sales_agents')->delete();
            DB::table('stores')->delete();

            $now = now();

            $stores = [
                'STORE-001' => (string) Str::uuid(),
                'STORE-002' => (string) Str::uuid(),
            ];

            DB::table('stores')->insert([
                ['id' => $stores['STORE-001'], 'store_code' => 'STORE-001', 'name' => '东区旗舰店', 'contact_name' => '测试店长A', 'contact_phone' => '0900-STORE-001', 'address' => '测试门店地址 1', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
                ['id' => $stores['STORE-002'], 'store_code' => 'STORE-002', 'name' => '南区合作店', 'contact_name' => '测试店长B', 'contact_phone' => '0900-STORE-002', 'address' => '测试门店地址 2', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $salesAgents = [
                'SALES-001' => (string) Str::uuid(),
                'SALES-002' => (string) Str::uuid(),
            ];

            DB::table('sales_agents')->insert([
                ['id' => $salesAgents['SALES-001'], 'agent_code' => 'SALES-001', 'name' => '业务员 A', 'phone' => '0900-SALES-001', 'region' => '东区', 'task_status' => 'AVAILABLE', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
                ['id' => $salesAgents['SALES-002'], 'agent_code' => 'SALES-002', 'name' => '业务员 B', 'phone' => '0900-SALES-002', 'region' => '南区', 'task_status' => 'AVAILABLE', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $users = [];
            foreach ([
                ['username' => 'admin001', 'display_name' => '超级管理员', 'role' => UserRole::SUPER_ADMIN->value, 'store_id' => null, 'sales_agent_id' => null],
                ['username' => 'audit001', 'display_name' => '审核员', 'role' => UserRole::AUDITOR->value, 'store_id' => null, 'sales_agent_id' => null],
                ['username' => 'cashier001', 'display_name' => '出纳', 'role' => UserRole::CASHIER->value, 'store_id' => null, 'sales_agent_id' => null],
                ['username' => 'sales001', 'display_name' => '业务员 A', 'role' => UserRole::SALES->value, 'store_id' => null, 'sales_agent_id' => $salesAgents['SALES-001']],
                ['username' => 'sales002', 'display_name' => '业务员 B', 'role' => UserRole::SALES->value, 'store_id' => null, 'sales_agent_id' => $salesAgents['SALES-002']],
                ['username' => 'store001', 'display_name' => '东区旗舰店', 'role' => UserRole::STORE->value, 'store_id' => $stores['STORE-001'], 'sales_agent_id' => null],
                ['username' => 'store002', 'display_name' => '南区合作店', 'role' => UserRole::STORE->value, 'store_id' => $stores['STORE-002'], 'sales_agent_id' => null],
            ] as $user) {
                $users[$user['username']] = DB::table('users')->insertGetId([
                    'name' => $user['display_name'],
                    'email' => $user['username'].'@demo.p2boyuan.local',
                    ...$user,
                    'password' => Hash::make('123456'),
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $applications = [
                ['no' => 'A20260530001', 'store' => 'STORE-001', 'creator' => 'store001', 'owner_role' => UserRole::AUDITOR->value, 'owner' => 'audit001', 'status' => ApplicationStatus::PENDING_ASSIGNMENT->value, 'customer' => '测试客户张先生', 'phone' => '0900-000-001', 'id_no' => 'TEST-ID-001', 'address' => '测试地址 1 号', 'brand' => 'Apple', 'model' => 'iPhone 16 Pro', 'color' => '沙漠金', 'capacity' => '256GB', 'imei' => 'TEST-IMEI-001', 'condition' => '外观轻微使用痕迹', 'sale_price' => 8999, 'loan_amount' => 7200, 'periods' => 12, 'agent' => null, 'task_status' => null],
                ['no' => 'A20260530002', 'store' => 'STORE-002', 'creator' => 'store002', 'owner_role' => UserRole::SALES->value, 'owner' => 'sales001', 'status' => ApplicationStatus::ASSIGNED->value, 'customer' => '测试客户李女士', 'phone' => '0900-000-002', 'id_no' => 'TEST-ID-002', 'address' => '测试地址 2 号', 'brand' => 'Apple', 'model' => 'iPhone 15', 'color' => '黑色', 'capacity' => '128GB', 'imei' => 'TEST-IMEI-002', 'condition' => '外观良好', 'sale_price' => 5999, 'loan_amount' => 4800, 'periods' => 9, 'agent' => 'SALES-001', 'task_status' => 'ASSIGNED'],
                ['no' => 'A20260530003', 'store' => 'STORE-001', 'creator' => 'store001', 'owner_role' => UserRole::SALES->value, 'owner' => 'sales002', 'status' => ApplicationStatus::INSPECTION_IN_PROGRESS->value, 'customer' => '测试客户王先生', 'phone' => '0900-000-003', 'id_no' => 'TEST-ID-003', 'address' => '测试地址 3 号', 'brand' => 'Apple', 'model' => 'iPhone 16', 'color' => '白色', 'capacity' => '256GB', 'imei' => 'TEST-IMEI-003', 'condition' => '待确认', 'sale_price' => 7999, 'loan_amount' => 6200, 'periods' => 12, 'agent' => 'SALES-002', 'task_status' => 'IN_PROGRESS'],
                ['no' => 'A20260530004', 'store' => 'STORE-001', 'creator' => 'store001', 'owner_role' => UserRole::AUDITOR->value, 'owner' => 'audit001', 'status' => ApplicationStatus::PENDING_REVIEW->value, 'customer' => '测试客户陈女士', 'phone' => '0900-000-004', 'id_no' => 'TEST-ID-004', 'address' => '测试地址 4 号', 'brand' => 'Apple', 'model' => 'iPhone 14 Pro', 'color' => '紫色', 'capacity' => '256GB', 'imei' => 'TEST-IMEI-004', 'condition' => '外观良好', 'sale_price' => 6999, 'loan_amount' => 5500, 'periods' => 9, 'agent' => 'SALES-001', 'task_status' => 'SUBMITTED'],
                ['no' => 'A20260530005', 'store' => 'STORE-001', 'creator' => 'store001', 'owner_role' => UserRole::STORE->value, 'owner' => 'store001', 'status' => ApplicationStatus::NEEDS_SUPPLEMENT->value, 'customer' => '测试客户周女士', 'phone' => '0900-000-005', 'id_no' => 'TEST-ID-005', 'address' => '测试地址 5 号', 'brand' => 'Apple', 'model' => 'iPhone 15 Pro', 'color' => '蓝色', 'capacity' => '256GB', 'imei' => 'TEST-IMEI-005', 'condition' => '验机通过', 'sale_price' => 7599, 'loan_amount' => 6000, 'periods' => 12, 'agent' => 'SALES-001', 'task_status' => 'SUBMITTED'],
                ['no' => 'A20260530006', 'store' => 'STORE-002', 'creator' => 'store002', 'owner_role' => UserRole::AUDITOR->value, 'owner' => 'audit001', 'status' => ApplicationStatus::REJECTED->value, 'customer' => '测试客户孙先生', 'phone' => '0900-000-006', 'id_no' => 'TEST-ID-006', 'address' => '测试地址 6 号', 'brand' => 'Apple', 'model' => 'iPhone 13', 'color' => '绿色', 'capacity' => '128GB', 'imei' => 'TEST-IMEI-006', 'condition' => '屏幕有明显划痕', 'sale_price' => 4599, 'loan_amount' => 3600, 'periods' => 6, 'agent' => 'SALES-002', 'task_status' => 'SUBMITTED'],
                ['no' => 'A20260530007', 'store' => 'STORE-002', 'creator' => 'store002', 'owner_role' => UserRole::CASHIER->value, 'owner' => 'cashier001', 'status' => ApplicationStatus::PENDING_PAYOUT->value, 'customer' => '测试客户赵先生', 'phone' => '0900-000-007', 'id_no' => 'TEST-ID-007', 'address' => '测试地址 7 号', 'brand' => 'Apple', 'model' => 'iPhone 16 Pro Max', 'color' => '原色', 'capacity' => '512GB', 'imei' => 'TEST-IMEI-007', 'condition' => '外观良好', 'sale_price' => 10999, 'loan_amount' => 8800, 'periods' => 12, 'agent' => 'SALES-001', 'task_status' => 'SUBMITTED'],
                ['no' => 'A20260530008', 'store' => 'STORE-001', 'creator' => 'store001', 'owner_role' => UserRole::STORE->value, 'owner' => 'store001', 'status' => ApplicationStatus::PAID->value, 'customer' => '测试客户刘女士', 'phone' => '0900-000-008', 'id_no' => 'TEST-ID-008', 'address' => '测试地址 8 号', 'brand' => 'Apple', 'model' => 'iPhone 15 Pro Max', 'color' => '黑色', 'capacity' => '256GB', 'imei' => 'TEST-IMEI-008', 'condition' => '外观良好', 'sale_price' => 8599, 'loan_amount' => 6800, 'periods' => 12, 'agent' => 'SALES-002', 'task_status' => 'SUBMITTED'],
            ];

            foreach ($applications as $index => $application) {
                $applicationId = (string) Str::uuid();
                $createdAt = $now->copy()->subMinutes(80 - ($index * 7));

                DB::table('applications')->insert([
                    'id' => $applicationId,
                    'application_no' => $application['no'],
                    'source_type' => 'STORE_SUBMIT',
                    'store_id' => $stores[$application['store']],
                    'created_by_user_id' => $users[$application['creator']],
                    'current_owner_role' => $application['owner_role'],
                    'current_owner_user_id' => $users[$application['owner']],
                    'status' => $application['status'],
                    'customer_name' => $application['customer'],
                    'customer_phone' => $application['phone'],
                    'id_type' => 'NATIONAL_ID',
                    'id_number' => $application['id_no'],
                    'customer_address' => $application['address'],
                    'brand' => $application['brand'],
                    'model' => $application['model'],
                    'color' => $application['color'],
                    'capacity' => $application['capacity'],
                    'imei' => $application['imei'],
                    'device_condition' => $application['condition'],
                    'sale_price' => $application['sale_price'],
                    'loan_amount' => $application['loan_amount'],
                    'periods' => $application['periods'],
                    'remark' => 'v0.1 business test application',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $this->addAttachment($applicationId, $users[$application['creator']], 'APPLICATION', '客户证件照-测试.png', $createdAt);
                $this->addAttachment($applicationId, $users[$application['creator']], 'APPLICATION', '设备照片-测试.png', $createdAt);
                $this->addStatusLog($applicationId, $users[$application['creator']], UserRole::STORE->value, null, ApplicationStatus::PENDING_ASSIGNMENT->value, '店家提交到店客户申请', $createdAt);

                if ($application['agent'] !== null) {
                    DB::table('inspection_tasks')->insert([
                        'id' => (string) Str::uuid(),
                        'application_id' => $applicationId,
                        'sales_agent_id' => $salesAgents[$application['agent']],
                        'assigned_by_user_id' => $users['audit001'],
                        'status' => $application['task_status'],
                        'inspection_note' => in_array($application['status'], [ApplicationStatus::PENDING_REVIEW->value, ApplicationStatus::NEEDS_SUPPLEMENT->value, ApplicationStatus::REJECTED->value, ApplicationStatus::PENDING_PAYOUT->value, ApplicationStatus::PAID->value], true) ? '设备外观正常，IMEI 与门店填报一致。' : null,
                        'started_at' => in_array($application['task_status'], ['IN_PROGRESS', 'SUBMITTED'], true) ? $createdAt->copy()->addMinutes(20) : null,
                        'submitted_at' => $application['task_status'] === 'SUBMITTED' ? $createdAt->copy()->addMinutes(35) : null,
                        'created_at' => $createdAt->copy()->addMinutes(10),
                        'updated_at' => $createdAt->copy()->addMinutes(10),
                    ]);

                    $this->addStatusLog($applicationId, $users['audit001'], UserRole::AUDITOR->value, ApplicationStatus::PENDING_ASSIGNMENT->value, ApplicationStatus::ASSIGNED->value, '审核员指派业务员验机', $createdAt->copy()->addMinutes(10));
                }

                if (in_array($application['status'], [ApplicationStatus::NEEDS_SUPPLEMENT->value, ApplicationStatus::REJECTED->value, ApplicationStatus::PENDING_PAYOUT->value, ApplicationStatus::PAID->value], true)) {
                    $toStatus = $application['status'];
                    $action = match ($toStatus) {
                        ApplicationStatus::NEEDS_SUPPLEMENT->value => 'REQUEST_SUPPLEMENT',
                        ApplicationStatus::REJECTED->value => 'REJECT',
                        default => 'APPROVE',
                    };

                    DB::table('review_records')->insert([
                        'id' => (string) Str::uuid(),
                        'application_id' => $applicationId,
                        'reviewer_user_id' => $users['audit001'],
                        'action' => $action,
                        'from_status' => ApplicationStatus::PENDING_REVIEW->value,
                        'to_status' => $toStatus === ApplicationStatus::PAID->value ? ApplicationStatus::PENDING_PAYOUT->value : $toStatus,
                        'note' => match ($action) {
                            'REQUEST_SUPPLEMENT' => '客户地址证明不清晰，请补充。',
                            'REJECT' => '资料不符合当前授信要求。',
                            default => '后台审核通过，进入待放款。',
                        },
                        'created_at' => $createdAt->copy()->addMinutes(45),
                        'updated_at' => $createdAt->copy()->addMinutes(45),
                    ]);
                }

                if (in_array($application['status'], [ApplicationStatus::PENDING_PAYOUT->value, ApplicationStatus::PAID->value], true)) {
                    $voucherAttachmentId = $application['status'] === ApplicationStatus::PAID->value
                        ? $this->addAttachment($applicationId, $users['cashier001'], 'PAYOUT', '打款凭证-'.$application['no'].'.png', $createdAt->copy()->addMinutes(65))
                        : null;

                    DB::table('payout_records')->insert([
                        'id' => (string) Str::uuid(),
                        'application_id' => $applicationId,
                        'cashier_user_id' => $application['status'] === ApplicationStatus::PAID->value ? $users['cashier001'] : null,
                        'amount' => $application['loan_amount'],
                        'status' => $application['status'] === ApplicationStatus::PAID->value ? 'PAID' : 'PENDING',
                        'voucher_attachment_id' => $voucherAttachmentId,
                        'paid_at' => $application['status'] === ApplicationStatus::PAID->value ? $createdAt->copy()->addMinutes(70) : null,
                        'remark' => $application['status'] === ApplicationStatus::PAID->value ? '出纳已确认打款。' : '等待出纳上传凭证并确认。',
                        'created_at' => $createdAt->copy()->addMinutes(50),
                        'updated_at' => $createdAt->copy()->addMinutes(50),
                    ]);
                }

                if ($application['status'] !== ApplicationStatus::PENDING_ASSIGNMENT->value) {
                    $this->addStatusLog($applicationId, $users[$application['owner']], $application['owner_role'], null, $application['status'], '测试数据当前状态：'.$application['status'], $createdAt->copy()->addMinutes(60));
                }
            }
        });
    }

    private function addAttachment(string $applicationId, int $uploadedByUserId, string $module, string $fileName, mixed $createdAt): string
    {
        $id = (string) Str::uuid();

        DB::table('attachments')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'module' => $module,
            'file_name' => $fileName,
            'file_path' => 'demo/'.$applicationId.'/'.$fileName,
            'mime_type' => str_ends_with($fileName, '.pdf') ? 'application/pdf' : 'image/png',
            'file_size' => 128000,
            'remark' => 'v0.1 business test attachment',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function addStatusLog(string $applicationId, int $actorUserId, string $actorRole, ?string $fromStatus, string $toStatus, string $message, mixed $createdAt): void
    {
        DB::table('status_logs')->insert([
            'id' => (string) Str::uuid(),
            'application_id' => $applicationId,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'metadata' => json_encode(['source' => 'DemoSeeder']),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
