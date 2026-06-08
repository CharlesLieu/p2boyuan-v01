# H5 业务体验与商家权限重构实施计划

> For `C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01`.

**Goal:** 按《手机分期回收平台_标准完整PRD_v1.3》重构商家端边界：商家只做入驻申请、查看本商家打款凭证和我的商家信息；同时继续优化业务员、财务 H5 体验和后台视觉。

**Architecture:** Vue 前端保留单页应用结构，新增 H5 商家门户组件；Laravel/PHP 后端新增商家入驻、商家凭证、后台商家管理和凭证管理接口；MySQL 新增必要表结构。原有用户申请、审核、验机、财务打款流程保留，但 `STORE` 权限从客户申请流程中移除。

**PRD Source:** `C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\docs\手机分期回收平台_标准完整PRD_v1.3.md`

## Current Conflicts To Fix

- Current `STORE` can create customer applications through `POST /applications`; v1.3 says merchant must not submit customer application data.
- Current `STORE` can submit supplements through `POST /applications/{id}/supplement`; v1.3 says merchant must not handle customer supplement material flow.
- Current store UI has order/intake/customer detail; v1.3 says merchant UI is onboarding + voucher list/detail + merchant info.
- Current database has `stores` but lacks onboarding review fields, qualification files, payment account fields, merchant voucher table, and voucher visibility rules.
- Current backend has `payout_records` for internal finance payout, but no merchant-facing voucher management module.

## Desired End State

商家 H5：

- 入驻申请表。
- 入驻状态页：未提交、审核中、审核通过、已驳回、已停用。
- 打款凭证列表：全部、待确认、已打款、已作废。
- 打款凭证详情：金额、时间、收款主体、脱敏账号、关联业务编号、图片/PDF 预览。
- 我的商家信息。

后台：

- 商家管理：入驻申请列表、详情、通过、驳回、启用、停用。
- 打款凭证管理：新增凭证、关联商家、上传凭证、作废凭证。

权限：

- 商家只能看自己的入驻信息和凭证。
- 商家不能看用户完整申请资料、合同、账单、逾期、锁机/解锁。
- 财务打款金额不能超过批准金额。

## Implementation Tasks

### Task 1: Add Merchant Domain Schema

Files:

- Create migration for `merchant_onboarding_applications`.
- Create migration for `merchant_payment_vouchers`.
- Modify `stores` table with onboarding/payment status fields if needed.
- Add models:
  - `backend/app/Models/MerchantOnboardingApplication.php`
  - `backend/app/Models/MerchantPaymentVoucher.php`

Schema direction:

- `merchant_onboarding_applications`
  - `id`
  - `store_id` nullable
  - `applicant_name`
  - `applicant_phone`
  - `applicant_id_number`
  - `merchant_name`
  - `merchant_address`
  - `contact_name`
  - `contact_phone`
  - `payment_method`
  - `payment_account`
  - `payment_account_name`
  - `payment_bank_or_channel`
  - `id_card_front_attachment_id`
  - `id_card_back_attachment_id`
  - `qualification_attachment_id`
  - `status`
  - `reviewer_user_id`
  - `reviewed_at`
  - `reject_reason`
  - timestamps

- `merchant_payment_vouchers`
  - `id`
  - `voucher_no`
  - `store_id`
  - `payout_record_id` nullable
  - `related_business_no` nullable
  - `amount`
  - `status`
  - `paid_at`
  - `payee_name`
  - `payee_account_masked`
  - `payer_name`
  - `voucher_attachment_id`
  - `remark`
  - `void_reason`
  - `created_by_user_id`
  - timestamps

Tests:

- Migration test asserts new tables and indexes exist.
- Seeder test asserts demo merchants and vouchers exist.

### Task 2: Add Merchant APIs

Files:

- `backend/routes/api.php`
- Create `backend/app/Http/Controllers/Api/MerchantController.php`
- Create request classes for onboarding submit/review and voucher create/void.

API direction:

- `POST /api/v1/merchant/onboarding`
  - Auth: public or authenticated demo route, depending on current login model.
  - Submits merchant onboarding application.

- `GET /api/v1/merchant/me`
  - Auth: `STORE`.
  - Returns store profile, onboarding status, masked payment info.

- `GET /api/v1/merchant/vouchers`
  - Auth: `STORE`.
  - Returns only vouchers where `store_id` equals current user store.

- `GET /api/v1/merchant/vouchers/{voucherId}`
  - Auth: `STORE`.
  - Rejects access to other store voucher.

- `GET /api/v1/admin/merchants`
  - Auth: `SUPER_ADMIN`.
  - Lists merchant onboarding/store records.

- `GET /api/v1/admin/merchants/{merchantId}`
  - Auth: `SUPER_ADMIN`.
  - Shows onboarding/detail.

- `POST /api/v1/admin/merchants/{merchantId}/approve`
  - Auth: `SUPER_ADMIN`.

- `POST /api/v1/admin/merchants/{merchantId}/reject`
  - Auth: `SUPER_ADMIN`.

- `GET /api/v1/admin/merchant-vouchers`
  - Auth: `SUPER_ADMIN`.

- `POST /api/v1/admin/merchant-vouchers`
  - Auth: `SUPER_ADMIN`.

- `POST /api/v1/admin/merchant-vouchers/{voucherId}/void`
  - Auth: `SUPER_ADMIN`.

Tests:

- Merchant can list own vouchers.
- Merchant cannot show other merchant voucher.
- Merchant cannot call customer application create/supplement endpoints.
- Super admin can approve/reject onboarding.
- Super admin can create and void voucher.

### Task 3: Tighten Existing Store Permissions

Files:

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/ApplicationController.php`
- Relevant tests in `backend/tests/Feature`.

Changes:

- Remove `STORE` from `POST /applications` authorization.
- Remove `STORE` from `POST /applications/{id}/supplement` authorization.
- Ensure `GET /applications` and `GET /applications/{id}` do not expose full customer applications to `STORE`, or reject `STORE` entirely if no merchant-scoped summary is required.
- Keep `SALES`, `AUDITOR`, `CASHIER`, `SUPER_ADMIN` behavior aligned with existing business flow.

Tests:

- `store001` receives 403 for application creation.
- `store001` receives 403 for supplement submission.
- `store001` receives 403 or restricted response for full application detail.

### Task 4: Seed Demo Data

Files:

- `backend/app/Services/DemoDataService.php`
- `backend/database/seeders/DemoSeeder.php`

Demo accounts:

- `store001 / 123456`: approved merchant with vouchers.
- `store002 / 123456`: rejected or pending merchant for onboarding state testing.
- Existing `sales001`, `audit001`, `cashier001`, `admin001` remain.

Demo data:

- One approved merchant.
- One pending onboarding application.
- One rejected onboarding application with reason.
- Three merchant vouchers:
  - One `PAID`.
  - One `PENDING_CONFIRMATION`.
  - One `VOIDED`.

Tests:

- Reset demo data recreates merchant onboarding and voucher demo records.

### Task 5: Frontend API Modules

Files:

- Create `frontend/src/api/modules/merchant.ts`
- Create `frontend/src/api/modules/adminMerchant.ts`
- Extend existing application/payout types only where necessary.

Types:

- `MerchantProfile`
- `MerchantOnboarding`
- `MerchantVoucher`
- `MerchantVoucherStatus`
- `MerchantOnboardingStatus`

Client functions:

- `getMerchantMe`
- `submitMerchantOnboarding`
- `listMerchantVouchers`
- `getMerchantVoucher`
- `listAdminMerchants`
- `approveMerchant`
- `rejectMerchant`
- `listAdminMerchantVouchers`
- `createAdminMerchantVoucher`
- `voidAdminMerchantVoucher`

Verification:

- TypeScript build passes.

### Task 6: Rewrite Store Workspace As Merchant H5

Files:

- `frontend/src/views/store/StoreWorkspace.vue`
- New shared H5 components as needed under `frontend/src/components/mobile`.

UI:

- Bottom tabs: `入驻`, `凭证`, `我的`.
- Approved merchant default tab: `凭证`.
- Pending merchant default tab: `入驻`.
- Rejected merchant default tab: `入驻`.

Screens:

- Onboarding form.
- Onboarding status.
- Voucher list with status filters.
- Voucher detail drawer/page.
- File preview for image/PDF.
- Merchant info page.

Rules:

- No customer application list.
- No order intake form.
- No supplement-material form.
- No customer full detail.

Verification:

- Mobile 390px width has no horizontal scroll.
- Approved merchant can view own voucher detail.
- Pending/rejected merchant sees onboarding state.
- No visible test-promotion wording; UI should only describe the production-style business system.

### Task 7: Add Admin Merchant Management UI

Files:

- Add admin merchant components/views.
- Update router/navigation.
- Update `frontend/src/components/layout/AppShell.vue` if required.

Screens:

- Merchant list.
- Onboarding detail.
- Approve/reject controls.
- Enable/disable controls if backend supports it in this iteration.

Verification:

- `admin001` can approve/reject merchant onboarding.
- Non-admin cannot access admin merchant APIs.

### Task 8: Add Admin Voucher Management UI

Files:

- Add admin voucher list/detail/create components.
- Reuse existing attachment upload component if available.

Screens:

- Voucher list.
- Create voucher form.
- Voucher detail.
- Void voucher.

Rules:

- Must choose merchant.
- Must enter amount, payment time, related business number.
- Must upload image/PDF.
- Merchant sees voucher after creation.

Verification:

- `admin001` creates voucher for store001.
- `store001` sees it.
- `store002` cannot see it.

### Task 9: Keep And Polish Sales/Cashier H5

Files:

- `frontend/src/views/sales/SalesWorkspace.vue`
- `frontend/src/views/cashier/CashierWorkspace.vue`

Sales:

- Keep mobile tabs and card list.
- Use vertical task detail.
- Make permissions clear: assist/follow-up only, no final audit/payment.

Cashier:

- Keep payout list/detail.
- Keep upload and preview.
- Ensure amount max equals approved loan/payout amount in UI and backend.

Tests:

- Existing payout over-limit test remains passing.
- UI disables over-limit submission.

### Task 10: Back Office Visual Polish

Files:

- Auditor/admin views and shared shell styles.

Rules:

- Auditor/admin keep desktop back-office style.
- List and detail must not overlap.
- Detail uses drawer or stable two-column layout with min/max widths.

Verification:

- Desktop 1440px screenshot: no overlap.
- Auditor can still assign/review/reject/request supplement.
- Admin reset demo data still works.

### Task 11: Documentation Sync

Files:

- `docs` API document.
- `docs` data table document.
- UAT document if current test flow changes.

Update required:

- Add merchant onboarding APIs.
- Add merchant voucher APIs.
- Add admin merchant APIs.
- Add admin merchant voucher APIs.
- Update removed or restricted store permissions.
- Add new database tables and field descriptions.
- Add role UAT cases for pending merchant, rejected merchant, approved merchant, own-voucher-only permission.

### Task 12: Full Verification

Commands:

```bash
cd backend
php artisan test
cd ../frontend
npm run build
```

Browser verification:

- Login `store001 / 123456`: approved merchant voucher flow.
- Login `store002 / 123456`: onboarding rejected/pending flow depending on seeded state.
- Login `admin001 / 123456`: merchant management and voucher management.
- Login `cashier001 / 123456`: payout over-limit and voucher preview.
- Login `audit001 / 123456`: application review remains available.

Acceptance:

- All tests pass.
- Frontend build passes.
- Mobile merchant screens have no horizontal scroll.
- Store role cannot access full customer applications.
- Merchant voucher visibility is scoped by merchant.

## Suggested Commit Plan

1. `feat: add merchant onboarding and voucher backend`
2. `feat: rewrite merchant h5 portal`
3. `feat: add admin merchant and voucher management`
4. `feat: polish h5 role workflows and back office`
5. `docs: sync prd v1.3 api and schema changes`
