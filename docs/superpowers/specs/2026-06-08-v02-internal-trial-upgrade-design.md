# v0.2 Internal Trial Upgrade Design

> This spec is the design checkpoint for upgrading the current P2BOYUAN demo baseline to v0.2 internal trial readiness. Implementation must not start until this spec is accepted and an implementation plan is written.

## Goal

Upgrade the current demo system into a controlled internal trial version by adding account governance, stricter permissions, an attachment center, stronger operation logs, list filters, and stable business numbering.

## Current Baseline

The project already has:

- Vue H5 frontend, Laravel API backend, MySQL, Docker deployment.
- Store, sales, auditor, cashier, and super admin roles.
- Application, assignment, inspection, review, supplement, payout, merchant onboarding, and merchant voucher flows.
- H5 competitor-style UI for store, sales, and cashier.
- Basic attachment upload and preview.
- Basic status logs and role middleware.
- v0.2 documentation folder with PRD, API, schema, UI, and UAT docs.

## Scope

### In Scope

- Super admin account management:
  - Create account.
  - Disable account.
  - Reset password.
  - Assign role.
  - Bind store account to store.
  - Bind sales account to sales agent profile.
  - Record password update time and last login time.
- Permission hardening:
  - Store only sees own merchant profile and merchant vouchers.
  - Sales only sees assigned applications/tasks.
  - Auditor handles assignment/review/supplement requests.
  - Cashier handles payout only.
  - Super admin has full access.
- Attachment center:
  - List attachments by application.
  - Group by module.
  - Preview images/PDF.
  - Download files.
  - Show uploader, role, upload time, size, and type.
- Operation logs:
  - Ensure all state-changing operations write logs.
  - Include before status, after status, operator, role, action, note, and timestamp.
- List filters:
  - Applications by status, store, sales agent, date range, keyword, pagination.
  - Payouts by status, store, date range, keyword.
  - Merchant onboarding by status, keyword, date range.
  - Merchant vouchers by status, store, date range, business number.
- Application number rule:
  - `A + yyyyMMdd + 4-digit daily sequence`.
  - New records use the rule; old demo records may remain unchanged.
- Documentation sync:
  - API doc.
  - Data table doc.
  - UAT doc.

### Out of Scope

- C-end user registration.
- C-end mall.
- Formal product inventory.
- Bill repayment.
- Real SMS, payment, bank, lock/unlock, or risk-control APIs.
- Multi-tenant SaaS features.

## Architecture

Keep the existing Laravel/Vue architecture. Add v0.2 capabilities with narrow service/controller additions instead of rewriting the core workflow.

Recommended backend boundaries:

- `AdminController` for account administration and high-level admin actions.
- `AttachmentController` for upload plus new list/detail/download metadata endpoints.
- `ApplicationController` for application list filters and status logs.
- `PayoutController` for payout filters and confirm validation.
- `MerchantController` for merchant filters and voucher management.
- A shared logging helper/service to avoid duplicated log-writing logic.
- A numbering service for application numbers.

Recommended frontend boundaries:

- Admin workspace gets account management and file center panels.
- Audit workspace gets better filters and attachment/log entry points.
- Cashier workspace gets payout filters and stronger voucher preview.
- Sales H5 keeps current order-center style and adds upload state/detail access.
- Store H5 keeps the latest merchant-only boundary.

## Data Model Notes

Before writing migrations, inspect current migrations and models. If fields already exist, do not add duplicates.

Likely changes:

- `users`: password update timestamp, disabled reason if absent.
- `attachments`: uploader id/role, module, file type, file size, storage path, related application.
- `status_logs`: before status, after status, operator id/name/role, action, remark.

## API Design

New or enhanced endpoints should be documented before merge:

- `POST /api/v1/admin/accounts`
- `PATCH /api/v1/admin/accounts/{id}`
- `POST /api/v1/admin/accounts/{id}/disable`
- `POST /api/v1/admin/accounts/{id}/reset-password`
- `GET /api/v1/applications/{applicationId}/attachments`
- `GET /api/v1/attachments/{attachmentId}`
- `GET /api/v1/attachments/{attachmentId}/download`
- Enhanced query params for application, payout, merchant, and voucher lists.

## Testing Strategy

Backend feature tests should lead the work:

- Account create/disable/reset login behavior.
- Role-based access denial.
- Attachment upload/list/download metadata.
- Required logs after each status transition.
- Filter combinations.
- Application number uniqueness.

Frontend verification:

- Admin account panel works on desktop.
- File center shows grouped attachments.
- H5 detail pages remain usable on mobile.
- No broken product images.
- Existing flows still work after filters/log changes.

## Acceptance Criteria

v0.2 is considered ready when:

- Super admin can create, disable, and reset accounts.
- Disabled accounts cannot log in.
- Role boundary tests pass.
- Attachments can be uploaded, previewed, downloaded, and traced to uploader.
- Every key state change has a log with before/after status.
- Lists support required filters.
- New application numbers follow the date + sequence rule.
- API, data table, and UAT docs are updated.
- Docker build succeeds and server deployment can be updated from GitHub.

## Design Self-Review

- No open placeholders remain.
- Scope is limited to internal trial readiness.
- Store role remains constrained to merchant onboarding/profile/vouchers.
- No real payment, bank, SMS, lock, repayment, or C-end functionality is included.
- Implementation can be decomposed into independent backend, frontend, and documentation tasks.
