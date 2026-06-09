# v0.2 Internal Trial Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the current system into v0.2 internal trial readiness with account governance, stricter permissions, attachment center, stronger logs, list filters, and stable application numbering.

**Architecture:** Keep the current Laravel API + Vue frontend + MySQL Docker architecture. Add narrow API endpoints and services around the existing flow rather than rewriting the core application lifecycle. Backend tests lead each functional area, then frontend views consume the new endpoints.

**Tech Stack:** Laravel 13, Sanctum, PHPUnit, MySQL, Vue 3, Pinia, Element Plus, Vite, Docker Compose.

---

## File Structure

### Backend

- Modify `backend/routes/api.php`: add v0.2 admin account, attachment list/detail/download, and filtered list endpoints where needed.
- Modify `backend/app/Http/Controllers/Api/AdminController.php`: add create account, disable account, reset password, update binding.
- Modify `backend/app/Http/Controllers/Api/ApplicationController.php`: add robust filters and permission restrictions for sales ownership.
- Modify `backend/app/Http/Controllers/Api/AttachmentController.php`: add list/detail/download metadata endpoints.
- Modify `backend/app/Http/Controllers/Api/PayoutController.php`: add filters and preserve amount ceiling validation.
- Modify `backend/app/Http/Controllers/Api/MerchantController.php`: add admin filters for onboarding and vouchers.
- Modify `backend/app/Models/User.php`: add fillable/casts/relations for account governance fields.
- Modify `backend/app/Models/Attachment.php`: ensure uploader/module/file metadata fields are exposed.
- Modify `backend/app/Models/StatusLog.php`: ensure before/after/operator/action/remark fields are exposed.
- Create `backend/app/Services/ApplicationNumberService.php`: generate `AyyyyMMddNNNN` application numbers.
- Create `backend/app/Services/StatusLogService.php`: centralize status log writing.
- Create `backend/app/Http/Requests/AdminAccountStoreRequest.php`: validate account creation.
- Create `backend/app/Http/Requests/AdminAccountUpdateRequest.php`: validate account binding/status updates.
- Create `backend/app/Http/Requests/AdminPasswordResetRequest.php`: validate password reset.
- Create migration `backend/database/migrations/2026_06_08_200001_add_v02_account_fields_to_users_table.php`.
- Create or modify backend feature tests:
  - `backend/tests/Feature/AdminAccountManagementTest.php`
  - `backend/tests/Feature/RoleBoundaryTest.php`
  - `backend/tests/Feature/AttachmentCenterTest.php`
  - `backend/tests/Feature/StatusLogCompletenessTest.php`
  - `backend/tests/Feature/ListFilterTest.php`
  - `backend/tests/Feature/ApplicationNumberTest.php`

### Frontend

- Modify `frontend/src/api/modules/applications.ts`: add account, attachment, and filter API helpers or split admin helpers into a new module.
- Create `frontend/src/api/modules/admin.ts`: account management and file center API functions.
- Modify `frontend/src/stores/applications.ts`: accept filter params and expose list reload helpers.
- Modify `frontend/src/views/admin/AdminWorkspace.vue`: add account management panel and file center panel.
- Modify `frontend/src/views/audit/AuditWorkspace.vue`: add filter controls and attachment/log entry points.
- Modify `frontend/src/views/cashier/CashierWorkspace.vue`: add payout filters and voucher preview consistency.
- Modify `frontend/src/views/sales/SalesWorkspace.vue`: keep H5 style, add upload/list state where needed.
- Modify `frontend/src/views/store/StoreWorkspace.vue`: keep merchant-only boundary, no customer application access.

### Docs

- Modify `docs/v0.2/v0.2_API接口完整文档.md`.
- Modify `docs/v0.2/v0.2_API接口变更说明.md`.
- Modify `docs/v0.2/v0.2_数据表变更说明.md`.
- Modify `docs/v0.2/v0.2_UAT测试说明文档.md`.
- Modify `docs/v0.2/README.md`.

---

## Task 1: Account Governance Data Model

**Files:**
- Create: `backend/database/migrations/2026_06_08_200001_add_v02_account_fields_to_users_table.php`
- Modify: `backend/app/Models/User.php`
- Test: `backend/tests/Feature/AdminAccountManagementTest.php`

- [ ] **Step 1: Write failing tests for account governance fields**

Add tests that assert users can store account lifecycle metadata:

```php
public function test_user_has_v02_account_governance_fields(): void
{
    $user = User::factory()->create([
        'status' => 'DISABLED',
        'password_updated_at' => now(),
        'disabled_at' => now(),
        'disabled_reason' => '内部试运行停用测试',
    ]);

    $this->assertSame('DISABLED', $user->status);
    $this->assertNotNull($user->password_updated_at);
    $this->assertNotNull($user->disabled_at);
    $this->assertSame('内部试运行停用测试', $user->disabled_reason);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd backend
php artisan test --filter=AdminAccountManagementTest
```

Expected: FAIL because the fields do not exist or are not fillable/cast.

- [ ] **Step 3: Add migration**

Create a migration that adds nullable fields if absent:

```php
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
```

- [ ] **Step 4: Update User model**

Add the new fields to `$fillable` and casts:

```php
'password_updated_at',
'disabled_at',
'disabled_reason',
```

Casts:

```php
'password_updated_at' => 'datetime',
'disabled_at' => 'datetime',
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
cd backend
php artisan test --filter=AdminAccountManagementTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations backend/app/Models/User.php backend/tests/Feature/AdminAccountManagementTest.php
git commit -m "feat: add v0.2 account governance fields"
```

---

## Task 2: Admin Account Management API

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/Api/AdminController.php`
- Create: `backend/app/Http/Requests/AdminAccountStoreRequest.php`
- Create: `backend/app/Http/Requests/AdminAccountUpdateRequest.php`
- Create: `backend/app/Http/Requests/AdminPasswordResetRequest.php`
- Test: `backend/tests/Feature/AdminAccountManagementTest.php`
- Docs: `docs/v0.2/v0.2_API接口完整文档.md`

- [ ] **Step 1: Write failing tests for create, disable, and reset password**

Tests must cover:

```php
public function test_super_admin_can_create_sales_account(): void
public function test_disabled_account_cannot_login(): void
public function test_super_admin_can_reset_password_and_old_password_fails(): void
public function test_non_super_admin_cannot_manage_accounts(): void
```

Use `actingAs($admin)` for API calls and `postJson('/api/v1/auth/login')` to verify login behavior.

- [ ] **Step 2: Run test to verify failure**

```bash
cd backend
php artisan test --filter=AdminAccountManagementTest
```

Expected: FAIL because endpoints are missing.

- [ ] **Step 3: Add routes**

Add under `role:SUPER_ADMIN`:

```php
Route::post('/admin/accounts', [AdminController::class, 'createAccount'])->middleware('role:SUPER_ADMIN');
Route::patch('/admin/accounts/{user}', [AdminController::class, 'updateAccount'])->middleware('role:SUPER_ADMIN');
Route::post('/admin/accounts/{user}/disable', [AdminController::class, 'disableAccount'])->middleware('role:SUPER_ADMIN');
Route::post('/admin/accounts/{user}/reset-password', [AdminController::class, 'resetPassword'])->middleware('role:SUPER_ADMIN');
```

- [ ] **Step 4: Implement request validation**

Create request classes with explicit rules:

```php
'username' => ['required', 'string', 'max:64', 'unique:users,username'],
'displayName' => ['required', 'string', 'max:100'],
'password' => ['required', 'string', 'min:6', 'max:64'],
'role' => ['required', Rule::in(['STORE', 'SALES', 'AUDITOR', 'CASHIER', 'SUPER_ADMIN'])],
'storeId' => ['nullable', 'uuid', 'exists:stores,id'],
'salesAgentId' => ['nullable', 'uuid', 'exists:sales_agents,id'],
```

For reset:

```php
'password' => ['required', 'string', 'min:6', 'max:64'],
```

- [ ] **Step 5: Implement controller methods**

Implementation requirements:

- Create uses `Hash::make()`.
- Store role requires `storeId`.
- Sales role requires `salesAgentId`.
- Disable sets `status = DISABLED`, `disabled_at`, and `disabled_reason`.
- Reset password updates hash and `password_updated_at`.

- [ ] **Step 6: Run tests**

```bash
cd backend
php artisan test --filter=AdminAccountManagementTest
```

Expected: PASS.

- [ ] **Step 7: Update API docs**

Add request/response examples for:

- `POST /api/v1/admin/accounts`
- `PATCH /api/v1/admin/accounts/{id}`
- `POST /api/v1/admin/accounts/{id}/disable`
- `POST /api/v1/admin/accounts/{id}/reset-password`

- [ ] **Step 8: Commit**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/Api/AdminController.php backend/app/Http/Requests docs/v0.2/v0.2_API接口完整文档.md backend/tests/Feature/AdminAccountManagementTest.php
git commit -m "feat: add v0.2 admin account management api"
```

---

## Task 3: Role Boundary Hardening

**Files:**
- Modify: `backend/app/Http/Controllers/Api/ApplicationController.php`
- Modify: `backend/app/Http/Controllers/Api/AttachmentController.php`
- Modify: `backend/app/Http/Controllers/Api/PayoutController.php`
- Modify: `backend/app/Http/Controllers/Api/MerchantController.php`
- Test: `backend/tests/Feature/RoleBoundaryTest.php`

- [ ] **Step 1: Write failing boundary tests**

Required tests:

```php
public function test_store_cannot_access_application_list(): void
public function test_sales_cannot_view_unassigned_application(): void
public function test_cashier_cannot_approve_application(): void
public function test_auditor_cannot_confirm_payout(): void
public function test_store_can_only_view_own_merchant_vouchers(): void
```

- [ ] **Step 2: Run tests**

```bash
cd backend
php artisan test --filter=RoleBoundaryTest
```

Expected: FAIL for any missing boundary.

- [ ] **Step 3: Add query guards**

For sales users, application queries must constrain by current owner or inspection task assignment:

```php
$query->where(function ($query) use ($user) {
    $query->where('current_owner_user_id', $user->id)
        ->orWhereHas('inspectionTasks', fn ($taskQuery) => $taskQuery->where('sales_agent_id', $user->sales_agent_id));
});
```

For store users, keep application access denied unless specifically allowed by a future version.

- [ ] **Step 4: Add resource guards**

Before returning application, payout, attachment, merchant, or voucher detail, verify the current user role may access that specific record.

- [ ] **Step 5: Run tests**

```bash
cd backend
php artisan test --filter=RoleBoundaryTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Api backend/tests/Feature/RoleBoundaryTest.php
git commit -m "test: enforce v0.2 role boundaries"
```

---

## Task 4: Application Number Service

**Files:**
- Create: `backend/app/Services/ApplicationNumberService.php`
- Modify: `backend/app/Http/Controllers/Api/ApplicationController.php`
- Modify: `backend/database/seeders/DemoSeeder.php`
- Test: `backend/tests/Feature/ApplicationNumberTest.php`

- [ ] **Step 1: Write failing tests**

Test format and uniqueness:

```php
public function test_application_number_uses_date_and_four_digit_sequence(): void
public function test_application_number_increments_within_same_day(): void
```

Expected format:

```text
A202606080001
```

- [ ] **Step 2: Run tests**

```bash
cd backend
php artisan test --filter=ApplicationNumberTest
```

Expected: FAIL until service exists.

- [ ] **Step 3: Implement service**

Service behavior:

- Prefix `A`.
- Date `Ymd`.
- Find max existing `application_no` matching the date.
- Increment final four digits.
- Pad to 4 digits.

- [ ] **Step 4: Use service in application creation**

Replace ad hoc number generation in `ApplicationController::store()` with:

```php
$applicationNo = app(ApplicationNumberService::class)->next();
```

- [ ] **Step 5: Run tests**

```bash
cd backend
php artisan test --filter=ApplicationNumberTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/ApplicationNumberService.php backend/app/Http/Controllers/Api/ApplicationController.php backend/tests/Feature/ApplicationNumberTest.php
git commit -m "feat: add v0.2 application numbering service"
```

---

## Task 5: Attachment Center Backend

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/Api/AttachmentController.php`
- Modify: `backend/app/Models/Attachment.php`
- Test: `backend/tests/Feature/AttachmentCenterTest.php`
- Docs: `docs/v0.2/v0.2_API接口完整文档.md`

- [ ] **Step 1: Write failing tests**

Required tests:

```php
public function test_authorized_user_can_list_application_attachments(): void
public function test_attachment_response_contains_uploader_and_file_metadata(): void
public function test_authorized_user_can_get_attachment_detail(): void
public function test_authorized_user_can_get_attachment_download_url(): void
public function test_store_cannot_list_application_attachments(): void
```

- [ ] **Step 2: Run tests**

```bash
cd backend
php artisan test --filter=AttachmentCenterTest
```

Expected: FAIL because list/detail/download endpoints are missing.

- [ ] **Step 3: Add routes**

```php
Route::get('/applications/{applicationId}/attachments', [AttachmentController::class, 'index']);
Route::get('/attachments/{attachmentId}', [AttachmentController::class, 'show']);
Route::get('/attachments/{attachmentId}/download', [AttachmentController::class, 'download']);
```

- [ ] **Step 4: Implement serializers**

Response must include:

```php
[
    'id' => $attachment->id,
    'applicationId' => $attachment->application_id,
    'module' => $attachment->module,
    'fileName' => $attachment->file_name,
    'filePath' => $attachment->file_path,
    'mimeType' => $attachment->mime_type,
    'fileSize' => $attachment->file_size,
    'uploaderId' => $attachment->uploaded_by_user_id,
    'uploaderName' => $attachment->uploader?->display_name,
    'uploaderRole' => $attachment->uploader?->role,
    'createdAt' => $attachment->created_at,
]
```

- [ ] **Step 5: Enforce access checks**

Reuse application-level visibility checks. Store users are denied for application attachment center in v0.2.

- [ ] **Step 6: Run tests**

```bash
cd backend
php artisan test --filter=AttachmentCenterTest
```

Expected: PASS.

- [ ] **Step 7: Update docs**

Document:

- `GET /api/v1/applications/{applicationId}/attachments`
- `GET /api/v1/attachments/{attachmentId}`
- `GET /api/v1/attachments/{attachmentId}/download`

- [ ] **Step 8: Commit**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/Api/AttachmentController.php backend/app/Models/Attachment.php backend/tests/Feature/AttachmentCenterTest.php docs/v0.2/v0.2_API接口完整文档.md
git commit -m "feat: add v0.2 attachment center api"
```

---

## Task 6: Status Log Completeness

**Files:**
- Create: `backend/app/Services/StatusLogService.php`
- Modify: `backend/app/Services/ApplicationStateService.php`
- Modify: `backend/app/Http/Controllers/Api/MerchantController.php`
- Modify: `backend/app/Http/Controllers/Api/PayoutController.php`
- Modify: `backend/app/Models/StatusLog.php`
- Test: `backend/tests/Feature/StatusLogCompletenessTest.php`

- [ ] **Step 1: Write failing tests**

Required tests:

```php
public function test_assignment_writes_before_after_status_log(): void
public function test_inspection_submission_writes_status_log(): void
public function test_review_approval_writes_status_log(): void
public function test_supplement_request_and_submit_write_logs(): void
public function test_payout_confirmation_writes_log(): void
public function test_admin_manual_status_change_writes_log(): void
```

- [ ] **Step 2: Run tests**

```bash
cd backend
php artisan test --filter=StatusLogCompletenessTest
```

Expected: FAIL where logs are missing or incomplete.

- [ ] **Step 3: Create StatusLogService**

Service method:

```php
public function record(
    Application $application,
    User $operator,
    string $action,
    ?string $beforeStatus,
    ?string $afterStatus,
    ?string $remark = null
): StatusLog
```

It must persist:

- application id
- action
- operator user id
- operator name
- operator role
- before status
- after status
- remark

- [ ] **Step 4: Replace duplicated log writes**

Use `StatusLogService` from application state transitions, payout confirm, and admin manual status change.

- [ ] **Step 5: Run tests**

```bash
cd backend
php artisan test --filter=StatusLogCompletenessTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services backend/app/Http/Controllers/Api backend/app/Models/StatusLog.php backend/tests/Feature/StatusLogCompletenessTest.php
git commit -m "feat: complete v0.2 operation logging"
```

---

## Task 7: List Filters Backend

**Files:**
- Modify: `backend/app/Http/Controllers/Api/ApplicationController.php`
- Modify: `backend/app/Http/Controllers/Api/PayoutController.php`
- Modify: `backend/app/Http/Controllers/Api/MerchantController.php`
- Modify: `frontend/src/api/modules/applications.ts`
- Modify: `frontend/src/api/modules/merchant.ts`
- Test: `backend/tests/Feature/ListFilterTest.php`
- Docs: `docs/v0.2/v0.2_API接口完整文档.md`

- [ ] **Step 1: Write failing tests**

Required tests:

```php
public function test_application_list_filters_by_status_store_sales_date_and_keyword(): void
public function test_payout_list_filters_by_status_store_date_and_keyword(): void
public function test_admin_merchants_filter_by_status_keyword_and_date(): void
public function test_admin_merchant_vouchers_filter_by_status_store_date_and_business_no(): void
```

- [ ] **Step 2: Run tests**

```bash
cd backend
php artisan test --filter=ListFilterTest
```

Expected: FAIL for unsupported query params.

- [ ] **Step 3: Add query filters**

Support:

- `status`
- `storeId`
- `salesAgentId`
- `dateFrom`
- `dateTo`
- `keyword`
- `page`
- `perPage`

Use `when()` clauses and keep pagination consistent.

- [ ] **Step 4: Update frontend API types**

Add typed filter payloads:

```ts
export interface ApplicationListFilters {
  status?: string
  storeId?: string
  salesAgentId?: string
  dateFrom?: string
  dateTo?: string
  keyword?: string
  page?: number
  perPage?: number
}
```

- [ ] **Step 5: Run tests**

```bash
cd backend
php artisan test --filter=ListFilterTest
```

Expected: PASS.

- [ ] **Step 6: Update API docs**

Add each new query param and response example.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/Api frontend/src/api/modules docs/v0.2/v0.2_API接口完整文档.md backend/tests/Feature/ListFilterTest.php
git commit -m "feat: add v0.2 list filters"
```

---

## Task 8: Admin Frontend Account Management and File Center

**Files:**
- Create: `frontend/src/api/modules/admin.ts`
- Modify: `frontend/src/views/admin/AdminWorkspace.vue`
- Modify: `frontend/src/api/modules/applications.ts`
- Test/Verify: `frontend npm run build`

- [ ] **Step 1: Add admin API module**

Create functions:

```ts
createAccount(payload)
updateAccount(id, payload)
disableAccount(id, payload)
resetAccountPassword(id, payload)
listApplicationAttachments(applicationId)
getAttachment(attachmentId)
getAttachmentDownload(attachmentId)
```

- [ ] **Step 2: Add account management panel**

In `AdminWorkspace.vue`, add a tab/panel with:

- account table
- create account form
- disable button
- reset password dialog
- role selector
- store/sales binding controls

- [ ] **Step 3: Add file center panel**

Add:

- application selector or current selected application context
- grouped attachment list
- preview button
- download button
- uploader and upload time display

- [ ] **Step 4: Build frontend**

```bash
cd frontend
npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/api/modules/admin.ts frontend/src/views/admin/AdminWorkspace.vue frontend/src/api/modules/applications.ts
git commit -m "feat: add v0.2 admin account and file center ui"
```

---

## Task 9: Audit, Cashier, Sales H5 Frontend Enhancements

**Files:**
- Modify: `frontend/src/views/audit/AuditWorkspace.vue`
- Modify: `frontend/src/views/cashier/CashierWorkspace.vue`
- Modify: `frontend/src/views/sales/SalesWorkspace.vue`
- Modify: `frontend/src/stores/applications.ts`
- Modify: `frontend/src/components/application/ApplicationDetail.vue`
- Verify: `frontend npm run build`

- [ ] **Step 1: Add audit filters**

Add status, store, sales agent, date range, and keyword controls. Wire to backend filters.

- [ ] **Step 2: Add cashier filters**

Add status, store, date range, and keyword controls. Keep amount ceiling validation UI.

- [ ] **Step 3: Add sales H5 upload state**

Show attachment upload progress/state and make uploaded files visible in order detail.

- [ ] **Step 4: Add detail attachment/log entry points**

In application detail, add explicit sections for:

- attachment preview
- status logs
- audit notes

- [ ] **Step 5: Build frontend**

```bash
cd frontend
npm run build
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/views frontend/src/stores frontend/src/components/application
git commit -m "feat: enhance v0.2 workflow filters and details"
```

---

## Task 10: Documentation Sync

**Files:**
- Modify: `docs/v0.2/v0.2_API接口完整文档.md`
- Modify: `docs/v0.2/v0.2_API接口变更说明.md`
- Modify: `docs/v0.2/v0.2_数据表变更说明.md`
- Modify: `docs/v0.2/v0.2_UAT测试说明文档.md`
- Modify: `docs/v0.2/README.md`

- [ ] **Step 1: Update API docs**

Document all newly added account, attachment, filter, and download endpoints with request/response examples.

- [ ] **Step 2: Update schema docs**

Document user account fields, attachment metadata fields, and log completeness rules.

- [ ] **Step 3: Update UAT**

Add cases for:

- create account
- disable account
- reset password
- role boundary denial
- attachment center
- list filters
- application numbering
- logs

- [ ] **Step 4: Run doc check**

```bash
rg -n "PLACEHOLDER|OLD_VERSION_MARKER" docs/v0.2
git diff --check
```

Expected: no output from `rg` except false positives in non-version numeric sections, and no errors from `git diff --check`.

- [ ] **Step 5: Commit**

```bash
git add docs/v0.2
git commit -m "docs: sync v0.2 implementation references"
```

---

## Task 11: Full Verification and Deployment Prep

**Files:**
- Modify if needed: `deploy/production-checklist.md`
- Verify only: backend tests, frontend build, Docker build

- [ ] **Step 1: Run backend tests**

```bash
cd backend
php artisan test
```

Expected: PASS.

- [ ] **Step 2: Run frontend build**

```bash
cd frontend
npm run build
```

Expected: PASS.

- [ ] **Step 3: Run Docker build**

```bash
docker compose build frontend backend
```

Expected: both images build successfully.

- [ ] **Step 4: Run Docker app locally**

```bash
docker compose up -d
docker compose ps
curl http://127.0.0.1/api/health
```

Expected:

```json
{"success":true,"data":{"status":"ok"}}
```

- [ ] **Step 5: Commit deployment docs if changed**

```bash
git add deploy/production-checklist.md
git commit -m "docs: update v0.2 deployment checklist"
```

Skip commit if no deployment docs changed.

---

## Self-Review

Spec coverage:

- Account management is covered by Tasks 1, 2, and 8.
- Permission hardening is covered by Task 3.
- Attachment center is covered by Tasks 5 and 8.
- Operation logs are covered by Task 6.
- List filters are covered by Tasks 7 and 9.
- Application numbering is covered by Task 4.
- Documentation sync is covered by Task 10.
- Verification and deployment readiness are covered by Task 11.

Placeholder scan:

- No vague implementation placeholders are intentionally left.

Type consistency:

- Backend routes, service names, and test names are consistent across tasks.
- Frontend module names are consistent with existing `frontend/src/api/modules/*` conventions.

Scope control:

- No C-end mall, repayment, SMS, payment, bank, lock/unlock, or risk-control integration is included.
