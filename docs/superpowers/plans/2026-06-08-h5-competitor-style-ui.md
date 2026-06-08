# H5 Competitor Style UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the mobile H5 experience for sales, cashier, and merchant roles into the approved light-blue competitor-style layout while keeping the existing business state machine and tightening role-specific workflows.

**Architecture:** Create a small shared H5 UI layer for status tabs, overview cards, order cards, bottom navigation, file preview, and iPhone imagery, then wire each role workspace to those primitives. Sales becomes the first full H5 rewrite because it owns the order/intake flow; cashier and merchant then reuse the same visual language; auditor and super admin remain desktop-style with only light visual cleanup.

**Tech Stack:** Vue 3, TypeScript, Pinia, Element Plus icons/components where already used, existing Laravel API contracts, Vite, Docker.

---

## File Structure

**Create**
- `frontend/src/components/h5/H5AppFrame.vue`  
  Mobile shell with title bar, scrollable content area, and bottom tab bar.
- `frontend/src/components/h5/H5OverviewCard.vue`  
  Light-blue white card summary area, matching the approved order center style.
- `frontend/src/components/h5/H5StatusTabs.vue`  
  Horizontally scrollable status tabs with active underline.
- `frontend/src/components/h5/H5OrderCard.vue`  
  Reusable order/task/voucher card with iPhone image, status chip, amount, and short metadata.
- `frontend/src/components/h5/H5FileUploadBox.vue`  
  Upload row/card that supports image/PDF preview and consistent labels.
- `frontend/src/components/h5/H5DetailSheet.vue`  
  Mobile detail sheet/page pattern for selected records.
- `frontend/src/utils/h5Format.ts`  
  Formatting helpers for money, status labels, dates, file URLs, and product image selection.

**Modify**
- `frontend/src/styles/theme.css`  
  Add H5 design tokens: light-blue background, card radius, sans-serif font stack, tab colors, status colors.
- `frontend/src/views/sales/SalesWorkspace.vue`  
  Replace current compact drawer/list UX with competitor-style task center and intake form.
- `frontend/src/views/cashier/CashierWorkspace.vue`  
  Replace desktop-like cashier page with payout center H5, file preview, and amount cap UX.
- `frontend/src/views/store/StoreWorkspace.vue`  
  Replace current merchant H5 with entry/voucher/my tabs using the shared H5 primitives.
- `frontend/src/views/audit/AuditWorkspace.vue`  
  Keep desktop structure, polish spacing/status chips only.
- `frontend/src/views/admin/AdminWorkspace.vue`  
  Keep desktop structure, polish merchant/voucher sections only.
- `frontend/src/api/modules/applications.ts`  
  Add narrow front-end type fields only if needed by the rewritten UI; do not change API behavior unless tests prove the UI needs it.

**Test/Verify**
- `npm run build` from `frontend`
- `npm run build:docker` from `frontend`
- Browser checks at local dev URL:
  - Login `sales001 / 123456`
  - Login `cashier001 / 123456`
  - Login `store001 / 123456`
  - Login `audit001 / 123456`
  - Login `admin001 / 123456`

---

## Task 1: Shared H5 Design Tokens And Utilities

**Files:**
- Create: `frontend/src/utils/h5Format.ts`
- Modify: `frontend/src/styles/theme.css`

- [ ] **Step 1: Add formatting and product image helpers**

Create `frontend/src/utils/h5Format.ts`:

```ts
import type { ApplicationStatus } from '../api/modules/applications'
import type { MerchantVoucherStatus } from '../api/modules/merchant'

export function h5Money(value: number | string | null | undefined, prefix = '¥') {
  return `${prefix}${Number(value ?? 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })}`
}

export function h5DateTime(value: string | null | undefined) {
  if (!value) return '暂无时间'
  return value.replace('T', ' ').slice(0, 16)
}

export function h5ApplicationStatusLabel(status: ApplicationStatus | string | null | undefined) {
  const labels: Record<string, string> = {
    DRAFT: '草稿',
    PENDING_ASSIGNMENT: '待指派',
    ASSIGNED: '待验机',
    INSPECTION_IN_PROGRESS: '验机中',
    PENDING_REVIEW: '待审核',
    NEEDS_SUPPLEMENT: '需补资料',
    REJECTED: '已驳回',
    PENDING_PAYOUT: '待打款',
    PAID: '已打款',
    COMPLETED: '已完成',
  }
  return labels[String(status ?? '')] ?? String(status ?? '未知')
}

export function h5VoucherStatusLabel(status: MerchantVoucherStatus | string | null | undefined) {
  const labels: Record<string, string> = {
    PENDING_CONFIRMATION: '待确认',
    PAID: '已打款',
    VOIDED: '已作废',
  }
  return labels[String(status ?? '')] ?? String(status ?? '未知')
}

export function h5AttachmentHref(path?: string | null) {
  if (!path) return null
  if (/^https?:\/\//.test(path) || path.startsWith('/')) return path
  return `/storage/${path}`
}

export function h5ProductImage(model?: string | null) {
  const normalized = String(model ?? '').toLowerCase()
  if (normalized.includes('17')) return '/demo/products/iphone-17-pro-max.png'
  if (normalized.includes('16')) return '/demo/products/iphone-16-pro-max.png'
  if (normalized.includes('15')) return '/demo/products/iphone-15-pro.png'
  if (normalized.includes('14')) return '/demo/products/iphone-14-pro.png'
  return '/demo/products/iphone-default.png'
}
```

- [ ] **Step 2: Add H5 theme tokens**

Append to `frontend/src/styles/theme.css`:

```css
:root {
  --h5-bg: #eaf1ff;
  --h5-card: #ffffff;
  --h5-soft: #f3f6ff;
  --h5-ink: #17234d;
  --h5-muted: #67759b;
  --h5-blue: #5d78ff;
  --h5-orange: #f07a4a;
  --h5-border: rgba(93, 120, 255, 0.16);
  --h5-shadow: 0 14px 32px rgba(61, 86, 150, 0.12);
  --h5-radius: 20px;
}

.role-mobile,
.merchant-h5,
.h5-page {
  font-family: "Microsoft YaHei", "PingFang SC", "Noto Sans CJK SC", Arial, sans-serif;
}
```

- [ ] **Step 3: Verify local type/build baseline**

Run:

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
```

Expected: either PASS, or existing unrelated TypeScript errors are recorded before the UI work proceeds.

- [ ] **Step 4: Commit**

```powershell
git add frontend/src/utils/h5Format.ts frontend/src/styles/theme.css
git commit -m "feat: add shared h5 formatting tokens"
```

---

## Task 2: Shared H5 Components

**Files:**
- Create: `frontend/src/components/h5/H5AppFrame.vue`
- Create: `frontend/src/components/h5/H5OverviewCard.vue`
- Create: `frontend/src/components/h5/H5StatusTabs.vue`
- Create: `frontend/src/components/h5/H5OrderCard.vue`
- Create: `frontend/src/components/h5/H5FileUploadBox.vue`
- Create: `frontend/src/components/h5/H5DetailSheet.vue`

- [ ] **Step 1: Create the mobile app frame**

Create `frontend/src/components/h5/H5AppFrame.vue`:

```vue
<script setup lang="ts">
export interface H5TabItem {
  key: string
  label: string
}

defineProps<{
  title: string
  tabs: H5TabItem[]
  activeTab: string
}>()

const emit = defineEmits<{
  tabChange: [key: string]
}>()
</script>

<template>
  <section class="h5-frame">
    <header class="h5-titlebar">
      <h1>{{ title }}</h1>
    </header>
    <main class="h5-frame-body">
      <slot />
    </main>
    <nav class="h5-bottom-tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :class="{ active: tab.key === activeTab }"
        @click="emit('tabChange', tab.key)"
      >
        {{ tab.label }}
      </button>
    </nav>
  </section>
</template>

<style scoped>
.h5-frame {
  min-height: 100dvh;
  background: var(--h5-bg);
  color: var(--h5-ink);
  padding-bottom: 74px;
}

.h5-titlebar {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 54px;
  background: #fff;
  border-bottom: 1px solid var(--h5-border);
}

.h5-titlebar h1 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 0;
}

.h5-frame-body {
  padding: 14px 12px 20px;
}

.h5-bottom-tabs {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  display: grid;
  grid-auto-flow: column;
  height: 64px;
  background: rgba(255, 255, 255, 0.96);
  border-top: 1px solid var(--h5-border);
  box-shadow: 0 -10px 24px rgba(61, 86, 150, 0.1);
}

.h5-bottom-tabs button {
  border: 0;
  background: transparent;
  color: var(--h5-muted);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
}

.h5-bottom-tabs button.active {
  color: var(--h5-blue);
}
</style>
```

- [ ] **Step 2: Create the overview card**

Create `frontend/src/components/h5/H5OverviewCard.vue`:

```vue
<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
  stats: Array<{ label: string; value: string | number }>
}>()
</script>

<template>
  <section class="h5-overview">
    <div class="h5-overview-head">
      <div>
        <p>{{ eyebrow }}</p>
        <h2>{{ title }}</h2>
      </div>
      <slot name="action" />
    </div>
    <div class="h5-overview-stats">
      <article v-for="stat in stats" :key="stat.label">
        <span>{{ stat.label }}</span>
        <strong>{{ stat.value }}</strong>
      </article>
    </div>
  </section>
</template>

<style scoped>
.h5-overview {
  border-radius: 0 0 24px 24px;
  background: var(--h5-card);
  padding: 24px 20px;
  box-shadow: var(--h5-shadow);
}

.h5-overview-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.h5-overview p {
  margin: 0 0 8px;
  color: #6477ba;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 6px;
}

.h5-overview h2 {
  margin: 0;
  font-size: 30px;
  line-height: 1.15;
  font-weight: 900;
}

.h5-overview-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 22px;
}

.h5-overview-stats article {
  min-height: 88px;
  border-radius: 16px;
  background: var(--h5-soft);
  padding: 16px;
}

.h5-overview-stats span {
  display: block;
  color: #6477ba;
  font-size: 13px;
}

.h5-overview-stats strong {
  display: block;
  margin-top: 12px;
  font-size: 24px;
  font-weight: 900;
}
</style>
```

- [ ] **Step 3: Create status tabs**

Create `frontend/src/components/h5/H5StatusTabs.vue`:

```vue
<script setup lang="ts">
defineProps<{
  tabs: Array<{ key: string; label: string }>
  active: string
}>()

const emit = defineEmits<{ change: [key: string] }>()
</script>

<template>
  <div class="h5-status-tabs">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      :class="{ active: tab.key === active }"
      @click="emit('change', tab.key)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<style scoped>
.h5-status-tabs {
  display: flex;
  gap: 24px;
  overflow-x: auto;
  margin: 16px 0;
  padding: 0 18px;
  min-height: 66px;
  align-items: center;
  background: #fff;
  border-radius: 20px;
  box-shadow: var(--h5-shadow);
}

.h5-status-tabs button {
  position: relative;
  flex: 0 0 auto;
  border: 0;
  background: transparent;
  color: var(--h5-muted);
  font: inherit;
  font-size: 15px;
  font-weight: 800;
  padding: 20px 0;
}

.h5-status-tabs button.active {
  color: var(--h5-ink);
}

.h5-status-tabs button.active::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 12px;
  height: 3px;
  border-radius: 999px;
  background: var(--h5-orange);
}
</style>
```

- [ ] **Step 4: Create the order card**

Create `frontend/src/components/h5/H5OrderCard.vue`:

```vue
<script setup lang="ts">
defineProps<{
  code: string
  title: string
  subtitle: string
  amount: string
  status: string
  image: string
}>()
</script>

<template>
  <article class="h5-order-card">
    <header>
      <div>
        <span>订单编号</span>
        <strong>{{ code }}</strong>
      </div>
      <em>{{ status }}</em>
    </header>
    <div class="h5-order-main">
      <img :src="image" :alt="title" />
      <div>
        <h3>{{ title }}</h3>
        <p>{{ subtitle }}</p>
        <p class="amount">{{ amount }}</p>
      </div>
    </div>
    <slot />
  </article>
</template>

<style scoped>
.h5-order-card {
  border-radius: var(--h5-radius);
  background: #fff;
  padding: 18px;
  box-shadow: var(--h5-shadow);
}

.h5-order-card + .h5-order-card {
  margin-top: 14px;
}

.h5-order-card header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.h5-order-card span {
  display: block;
  color: #6477ba;
  font-size: 12px;
  margin-bottom: 4px;
}

.h5-order-card strong {
  display: block;
  color: #233d88;
  font-size: 16px;
  font-weight: 900;
}

.h5-order-card em {
  align-self: flex-start;
  border-radius: 999px;
  background: #eef3ff;
  color: var(--h5-blue);
  font-style: normal;
  font-size: 13px;
  font-weight: 900;
  padding: 8px 12px;
}

.h5-order-main {
  display: grid;
  grid-template-columns: 104px minmax(0, 1fr);
  gap: 16px;
  align-items: center;
  margin-top: 18px;
}

.h5-order-main img {
  width: 104px;
  height: 104px;
  object-fit: contain;
  border-radius: 18px;
  background: #f7f9ff;
}

.h5-order-main h3 {
  margin: 0 0 10px;
  color: #17306f;
  font-size: 18px;
  line-height: 1.25;
  font-weight: 900;
}

.h5-order-main p {
  margin: 0 0 8px;
  color: #526798;
  font-size: 13px;
  line-height: 1.45;
}

.h5-order-main .amount {
  color: #203a84;
  font-size: 16px;
  font-weight: 900;
}
</style>
```

- [ ] **Step 5: Create file upload box**

Create `frontend/src/components/h5/H5FileUploadBox.vue`:

```vue
<script setup lang="ts">
defineProps<{
  label: string
  description?: string
  fileName?: string | null
  previewable?: boolean
}>()

const emit = defineEmits<{
  upload: []
  preview: []
}>()
</script>

<template>
  <section class="h5-upload-box">
    <div>
      <strong>{{ label }}</strong>
      <p v-if="description">{{ description }}</p>
    </div>
    <button type="button" @click="emit('upload')">{{ fileName ? '重新上传' : '+ 上传文件' }}</button>
    <button v-if="fileName && previewable" type="button" class="ghost" @click="emit('preview')">
      预览 {{ fileName }}
    </button>
  </section>
</template>

<style scoped>
.h5-upload-box {
  border-radius: 16px;
  background: #fff;
  border: 1px dashed var(--h5-border);
  padding: 14px;
}

.h5-upload-box strong {
  display: block;
  font-size: 15px;
  color: var(--h5-ink);
}

.h5-upload-box p {
  margin: 6px 0 12px;
  color: var(--h5-muted);
  font-size: 12px;
}

.h5-upload-box button {
  width: 100%;
  min-height: 42px;
  border: 0;
  border-radius: 14px;
  background: var(--h5-blue);
  color: #fff;
  font-weight: 900;
}

.h5-upload-box button.ghost {
  margin-top: 8px;
  background: #eef3ff;
  color: var(--h5-blue);
}
</style>
```

- [ ] **Step 6: Create detail sheet**

Create `frontend/src/components/h5/H5DetailSheet.vue`:

```vue
<script setup lang="ts">
defineProps<{
  visible: boolean
  title: string
}>()

const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="h5-sheet-mask" @click.self="emit('close')">
      <section class="h5-sheet">
        <header>
          <h2>{{ title }}</h2>
          <button type="button" @click="emit('close')">×</button>
        </header>
        <slot />
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.h5-sheet-mask {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(15, 23, 42, 0.44);
  display: flex;
  align-items: flex-end;
}

.h5-sheet {
  width: 100%;
  max-height: 88dvh;
  overflow-y: auto;
  border-radius: 18px 18px 0 0;
  background: #fff;
  padding: 18px;
}

.h5-sheet header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.h5-sheet h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 900;
}

.h5-sheet button {
  border: 0;
  background: transparent;
  font-size: 28px;
  line-height: 1;
  color: var(--h5-ink);
}
</style>
```

- [ ] **Step 7: Verify shared components compile**

Run:

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
```

Expected: PASS.

- [ ] **Step 8: Commit**

```powershell
git add frontend/src/components/h5 frontend/src/utils/h5Format.ts frontend/src/styles/theme.css
git commit -m "feat: add shared h5 ui components"
```

---

## Task 3: Sales Mobile Task Center And Intake Desk

**Files:**
- Modify: `frontend/src/views/sales/SalesWorkspace.vue`

- [ ] **Step 1: Replace sales layout with H5 tabs**

Use `H5AppFrame` with tabs:

```ts
const activeTab = ref<'tasks' | 'intake' | 'mine'>('tasks')
const salesTabs = [
  { key: 'tasks', label: '任务' },
  { key: 'intake', label: '录单台' },
  { key: 'mine', label: '我的' },
]
```

The title is `录单台` when active tab is `intake`, otherwise `我的订单`.

- [ ] **Step 2: Add status filtering**

Add:

```ts
const statusFilter = ref<ApplicationStatus | 'ALL'>('ALL')
const statusTabs = [
  { key: 'ALL', label: '全部' },
  { key: 'ASSIGNED', label: '待验机' },
  { key: 'INSPECTION_IN_PROGRESS', label: '验机中' },
  { key: 'NEEDS_SUPPLEMENT', label: '需补资料' },
  { key: 'PENDING_REVIEW', label: '待审核' },
  { key: 'REJECTED', label: '已驳回' },
  { key: 'PAID', label: '已打款' },
]
const filteredApplications = computed(() =>
  statusFilter.value === 'ALL'
    ? applications.items
    : applications.items.filter((item) => item.status === statusFilter.value),
)
```

- [ ] **Step 3: Implement order card selection as immediate detail sheet**

In the card click handler:

```ts
async function openApplication(applicationId: string) {
  await applications.select(applicationId)
  detailVisible.value = true
}
```

Expected mobile behavior: tapping a card opens a detail sheet immediately instead of making the user scroll to the bottom.

- [ ] **Step 4: Add sales intake form sections**

Add local form state:

```ts
const intakeForm = reactive({
  storeCode: '0001',
  salesAgentName: auth.user?.display_name ?? '业务员 A',
  productModel: 'Apple iPhone 16 Pro Max',
  color: '白色',
  capacity: '256GB',
  periodDays: 210,
  salePrice: 8999,
  loanAmount: 3215,
  customerName: '',
  customerPhone: '',
  idType: '身份证',
  idNumber: '',
  customerAddress: '',
  emergencyName: '',
  emergencyRelation: '',
  emergencyPhone: '',
})
```

Render sections matching the approved preview:
- 基础业务信息
- 账单配置
- 客户基础信息
- 紧急联系人
- 资料上传区

Buttons:
- `保存草稿`: local `ElMessage.success('草稿已保存。')`
- `提交`: call existing inspection/app operation only if current backend supports it; otherwise keep as UX placeholder with clear disabled state for v1.3 front-end preview.

- [ ] **Step 5: Preserve existing sales operations**

Keep existing methods:
- `startTask`
- `submitTask`
- `rejectTask`
- `submitSupplement`

Move their buttons into the selected detail sheet so the workflow remains:
`待验机 -> 验机中 -> 待审核` or `需补资料 -> 提交补资料`.

- [ ] **Step 6: Verify sales role manually**

Run dev server:

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run dev -- --host 127.0.0.1
```

Open:
- `http://127.0.0.1:<port>/login`
- Login `sales001 / 123456`

Expected:
- Mobile viewport shows light-blue `我的订单`.
- Bottom tabs show `任务 / 录单台 / 我的`.
- Cards contain iPhone images.
- Tapping a card opens detail sheet immediately.
- Existing start/submit/reject/supplement actions are still reachable.

- [ ] **Step 7: Build**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
npm run build:docker
```

Expected: both PASS.

- [ ] **Step 8: Commit**

```powershell
git add frontend/src/views/sales/SalesWorkspace.vue
git commit -m "feat: redesign sales h5 task center"
```

---

## Task 4: Cashier Mobile Payout Center

**Files:**
- Modify: `frontend/src/views/cashier/CashierWorkspace.vue`

- [ ] **Step 1: Replace desktop wrapper with H5 payout frame**

Use tabs:

```ts
const activeTab = ref<'payouts' | 'vouchers' | 'mine'>('payouts')
const cashierTabs = [
  { key: 'payouts', label: '打款' },
  { key: 'vouchers', label: '凭证' },
  { key: 'mine', label: '我的' },
]
```

Overview card:
- `待打款`: `pendingPayouts.length`
- `已打款`: `h5Money(paidAmount.value)`

- [ ] **Step 2: Render payout cards**

Each payout card uses:
- code: `payout.application?.applicationNo ?? payout.id`
- title: `payout.application?.model ?? '待打款订单'`
- subtitle: `商家：${payout.application?.storeName ?? '未记录'}`
- amount: `最高打款 ${h5Money(payout.amount)}`
- status: `payout.status === 'PENDING' ? '待打款' : '已打款'`
- image: `h5ProductImage(payout.application?.model)`

- [ ] **Step 3: Keep hard amount cap**

Keep and surface current guard:

```ts
if (Number(form.amount) > selectedPayoutAmount.value) {
  ElMessage.warning(`打款金额不能超过申请贷款金额 ${money(selectedPayoutAmount.value)}。`)
  form.amount = selectedPayoutAmount.value
  return
}
```

Add input `max`:

```vue
<el-input-number v-model="form.amount" :min="0" :max="selectedPayoutAmount" />
```

- [ ] **Step 4: Add upload preview workflow**

Use `H5FileUploadBox` for the voucher file. After upload, show:
- file name
- file size
- `预览` button

`previewVoucher()` continues to open `voucherPreviewUrl`.

- [ ] **Step 5: Verify cashier role manually**

Login `cashier001 / 123456`.

Expected:
- Mobile viewport shows `我的打款` / `打款中心`.
- Payout card images render.
- Selecting a payout opens a vertical detail/payment panel.
- Amount cannot exceed payout amount.
- Upload accepts PNG/JPG/WEBP/PDF and preview opens the uploaded file.

- [ ] **Step 6: Build and commit**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
npm run build:docker
git add frontend/src/views/cashier/CashierWorkspace.vue
git commit -m "feat: redesign cashier h5 payout center"
```

---

## Task 5: Merchant Mobile Entry And Voucher Center

**Files:**
- Modify: `frontend/src/views/store/StoreWorkspace.vue`

- [ ] **Step 1: Replace merchant page shell with shared H5 frame**

Use tabs:

```ts
const activeTab = ref<'onboarding' | 'vouchers' | 'mine'>('vouchers')
const storeTabs = [
  { key: 'onboarding', label: '入驻' },
  { key: 'vouchers', label: '凭证' },
  { key: 'mine', label: '我的' },
]
```

Keep current rule:
- if onboarding status is not approved, default to `onboarding`.
- if approved and user is on onboarding, move to `vouchers`.

- [ ] **Step 2: Render onboarding as competitor-style审核流程**

Use light-blue status blocks:
- 未提交资料
- 平台审核中
- 已通过
- 已驳回

Show reject reason only when status is `REJECTED`.

- [ ] **Step 3: Render voucher cards**

Each voucher card uses:
- code: `voucher.voucherNo`
- title: `voucher.relatedBusinessNo ?? '打款凭证'`
- subtitle: `商家：${voucher.storeName ?? profile?.name ?? '本商家'}`
- amount: `h5Money(voucher.amount)`
- status: `h5VoucherStatusLabel(voucher.status)`
- image: `h5ProductImage(voucher.relatedBusinessNo)`

Card click opens detail sheet with voucher file preview.

- [ ] **Step 4: Preserve merchant permission wording**

Do not show customer full application data, contract, billing, overdue, lock/unlock, or supplement controls. Merchant pages show only:
- merchant profile
- onboarding status and own submitted onboarding material names
- merchant payment vouchers and voucher details

- [ ] **Step 5: Verify merchant role manually**

Login `store001 / 123456`.

Expected:
- Bottom tabs `入驻 / 凭证 / 我的`.
- Approved store sees own vouchers.
- Voucher detail can preview PNG/PDF.
- No customer full loan application details appear.

- [ ] **Step 6: Build and commit**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
npm run build:docker
git add frontend/src/views/store/StoreWorkspace.vue
git commit -m "feat: redesign merchant h5 voucher center"
```

---

## Task 6: Desktop Auditor And Super Admin Light Polish

**Files:**
- Modify: `frontend/src/views/audit/AuditWorkspace.vue`
- Modify: `frontend/src/views/admin/AdminWorkspace.vue`
- Modify if necessary: `frontend/src/components/application/ApplicationList.vue`
- Modify if necessary: `frontend/src/components/application/ApplicationDetail.vue`

- [ ] **Step 1: Keep desktop workflow unchanged**

Do not alter API calls for:
- assign
- approve
- reject
- request supplement to SALES
- merchant onboarding approve/reject
- merchant voucher create/void

- [ ] **Step 2: Polish spacing and status readability**

Apply only visual changes:
- no overlapping list/detail columns
- detail panel uses fixed max width or responsive grid
- status chips are readable
- merchant/voucher admin sections use consistent card spacing

For the previous desktop overlap bug, make the workbench grid:

```css
.application-workbench {
  display: grid;
  grid-template-columns: minmax(420px, 0.9fr) minmax(520px, 1.1fr);
  gap: 24px;
  align-items: start;
}

@media (max-width: 1180px) {
  .application-workbench {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 3: Verify desktop roles**

Login:
- `audit001 / 123456`
- `admin001 / 123456`

Expected:
- No two-column overlap at 1366px, 1440px, or 1920px width.
- Auditor can still request supplement only to SALES.
- Admin can still approve/reject merchants and create/void vouchers.

- [ ] **Step 4: Build and commit**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
npm run build:docker
git add frontend/src/views/audit/AuditWorkspace.vue frontend/src/views/admin/AdminWorkspace.vue frontend/src/components/application/ApplicationList.vue frontend/src/components/application/ApplicationDetail.vue
git commit -m "style: polish desktop review workspaces"
```

---

## Task 7: Final Verification And Deployment Notes

**Files:**
- Modify: `docs/v1.3_UI改造说明.md`

- [ ] **Step 1: Add UI change notes**

Create or update `docs/v1.3_UI改造说明.md` with:

```md
# v1.3 H5 UI 改造说明

## 改造范围
- 商家端：入驻、凭证、我的。
- 业务员端：任务中心、录单台、我的。
- 出纳端：打款中心、凭证、我的。
- 审核员/超级管理员：保留电脑后台风格，仅优化布局和视觉层级。

## 不变范围
- 业务状态机不变。
- 商家权限不扩大。
- 审核员补资料仍只回到业务员。
- 出纳打款金额不得超过申请贷款金额。

## 测试账号
- store001 / 123456
- sales001 / 123456
- cashier001 / 123456
- audit001 / 123456
- admin001 / 123456
```

- [ ] **Step 2: Run final frontend builds**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\frontend
npm run build
npm run build:docker
```

Expected: both PASS.

- [ ] **Step 3: Run backend smoke tests if Docker is available locally**

```powershell
cd C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01
docker compose ps
```

If containers are running, verify:
- `GET /api/health`
- login `sales001`, `cashier001`, `store001`
- list applications, payouts, merchant vouchers

- [ ] **Step 4: Browser visual checks**

Use mobile viewport screenshots for:
- Sales task list
- Sales intake desk
- Cashier payout center
- Merchant voucher center

Use desktop screenshots for:
- Auditor workspace
- Admin merchant/voucher workspace

Expected:
- No calligraphy-style font.
- No visible “路演 / 远程彩排 / 演示版本” wording.
- No text overlap.
- H5 pages match the approved light-blue layout.

- [ ] **Step 5: Final commit**

```powershell
git add docs/v1.3_UI改造说明.md
git commit -m "docs: document v1.3 h5 ui redesign"
```

---

## Self-Review

**Spec coverage:**  
Covered shared H5 visual language, sales task center, sales intake desk, cashier payout and voucher preview, merchant onboarding/voucher/my tabs, iPhone images, sans-serif font, role-specific permissions, and desktop-only light polish for auditor/admin.

**Placeholder scan:**  
No placeholder markers or intentionally vague implementation steps remain. Steps include exact files, representative code, commands, and expected outcomes.

**Type consistency:**  
The plan reuses existing exported types from `applications.ts` and `merchant.ts`. New helper names are consistent: `h5Money`, `h5DateTime`, `h5ApplicationStatusLabel`, `h5VoucherStatusLabel`, `h5AttachmentHref`, `h5ProductImage`.

**Execution recommendation:**  
Use Subagent-Driven execution and complete one role at a time. The first checkpoint should be after Task 3 because sales is the biggest workflow change and determines whether the shared H5 primitives feel correct.
