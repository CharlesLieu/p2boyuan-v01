<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Money, Refresh } from '@element-plus/icons-vue'
import H5AppFrame from '../../components/h5/H5AppFrame.vue'
import H5DetailSheet from '../../components/h5/H5DetailSheet.vue'
import H5FileUploadBox from '../../components/h5/H5FileUploadBox.vue'
import H5OrderCard from '../../components/h5/H5OrderCard.vue'
import H5OverviewCard from '../../components/h5/H5OverviewCard.vue'
import H5StatusTabs from '../../components/h5/H5StatusTabs.vue'
import {
  confirmPayout,
  listPayouts,
  uploadAttachment,
  type AttachmentInfo,
  type PayoutRecord,
} from '../../api/modules/applications'
import { h5Money, h5ProductImage } from '../../utils/h5Format'

type CashierTab = 'payouts' | 'vouchers' | 'mine'
type PayoutStatusFilter = 'ALL' | 'PENDING' | 'PAID' | 'VOUCHER_ISSUE'

const payouts = ref<PayoutRecord[]>([])
const selectedPayoutId = ref<string | null>(null)
const loading = ref(false)
const operating = ref(false)
const uploading = ref(false)
const detailVisible = ref(false)
const voucherInput = ref<HTMLInputElement | null>(null)
const voucherAttachment = ref<AttachmentInfo | null>(null)
const activeTab = ref<CashierTab>('payouts')
const payoutFilter = ref<PayoutStatusFilter>('ALL')

const cashierTabs = [
  { key: 'payouts', label: '打款' },
  { key: 'vouchers', label: '凭证' },
  { key: 'mine', label: '我的' },
]
const payoutStatusTabs: Array<{ key: PayoutStatusFilter; label: string }> = [
  { key: 'ALL', label: '全部' },
  { key: 'PENDING', label: '待打款' },
  { key: 'PAID', label: '已打款' },
  { key: 'VOUCHER_ISSUE', label: '凭证异常' },
]

const form = reactive({
  amount: 0,
  paidAt: dateTimeValue(),
  remark: '线下打款已完成，凭证已上传。',
})

const selectedPayout = computed(
  () => payouts.value.find((payout) => payout.id === selectedPayoutId.value) ?? null,
)
const selectedPayoutAmount = computed(() => Number(selectedPayout.value?.amount ?? 0))
const selectedVoucher = computed(
  () => voucherAttachment.value ?? selectedPayout.value?.voucherAttachment ?? selectedPayout.value?.voucher ?? null,
)
const voucherPreviewUrl = computed(() =>
  selectedVoucher.value ? attachmentHref(selectedVoucher.value) : null,
)
const pendingPayouts = computed(() => payouts.value.filter((payout) => payout.status === 'PENDING'))
const paidPayouts = computed(() => payouts.value.filter((payout) => payout.status === 'PAID'))
const voucherPayouts = computed(() =>
  payouts.value.filter(
    (payout) => payout.status === 'PAID' || Boolean(payout.voucherAttachment ?? payout.voucher),
  ),
)
const paidAmount = computed(() =>
  paidPayouts.value.reduce((total, payout) => total + Number(payout.amount ?? 0), 0),
)
const overviewStats = computed(() => [
  { label: '待打款', value: pendingPayouts.value.length },
  { label: '已打款', value: h5Money(paidAmount.value) },
])
const mineStats = computed(() => [
  { label: '待打款', value: pendingPayouts.value.length },
  { label: '已打款金额', value: h5Money(paidAmount.value) },
])
const filteredPayouts = computed(() => {
  if (payoutFilter.value === 'ALL') {
    return payouts.value
  }

  if (payoutFilter.value === 'VOUCHER_ISSUE') {
    return []
  }

  return payouts.value.filter((payout) => payout.status === payoutFilter.value)
})
const selectedVoucherFileName = computed(() => selectedVoucher.value?.fileName ?? null)
const canPreviewVoucher = computed(() => Boolean(voucherPreviewUrl.value))
const canConfirmSelected = computed(
  () =>
    Boolean(selectedPayout.value) &&
    selectedPayout.value?.status === 'PENDING' &&
    Boolean(voucherAttachment.value),
)

async function fetchPayouts() {
  loading.value = true

  try {
    payouts.value = await listPayouts()
    if (!selectedPayoutId.value || !payouts.value.some((item) => item.id === selectedPayoutId.value)) {
      selectedPayoutId.value = payouts.value[0]?.id ?? null
    }
    syncFormFromSelected()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function confirmSelectedPayout() {
  if (!selectedPayout.value) {
    return
  }

  if (!voucherAttachment.value) {
    ElMessage.warning('请先上传打款凭证。')
    return
  }

  if (Number(form.amount) > selectedPayoutAmount.value) {
    ElMessage.warning(`打款金额不能超过申请贷款金额 ${h5Money(selectedPayoutAmount.value)}。`)
    form.amount = selectedPayoutAmount.value
    return
  }

  operating.value = true

  try {
    await confirmPayout(selectedPayout.value.id, {
      amount: form.amount || Number(selectedPayout.value.amount),
      paidAt: form.paidAt,
      remark: form.remark,
      voucher: {
        fileName: voucherAttachment.value.fileName ?? 'payout-voucher',
        filePath: voucherAttachment.value.filePath ?? '',
        mimeType: voucherAttachment.value.mimeType ?? null,
        fileSize: voucherAttachment.value.fileSize ?? null,
        remark: '出纳上传的打款凭证。',
      },
    })
    ElMessage.success('打款已确认。')
    voucherAttachment.value = null
    await fetchPayouts()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

function selectPayout(payout: PayoutRecord) {
  selectedPayoutId.value = payout.id
  voucherAttachment.value = null
  syncFormFromSelected()
}

function openPayoutDetail(payout: PayoutRecord) {
  selectPayout(payout)
  detailVisible.value = true
}

function changeCashierTab(key: string) {
  if (key === 'payouts' || key === 'vouchers' || key === 'mine') {
    activeTab.value = key
  }
}

function changePayoutFilter(key: string) {
  if (payoutStatusTabs.some((tab) => tab.key === key)) {
    payoutFilter.value = key as PayoutStatusFilter
  }
}

function triggerVoucherUpload() {
  voucherInput.value?.click()
}

function previewVoucher() {
  if (voucherPreviewUrl.value) {
    window.open(voucherPreviewUrl.value, '_blank', 'noopener,noreferrer')
  }
}

async function handleVoucherChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''

  if (!file || !selectedPayout.value?.applicationId) {
    return
  }

  uploading.value = true

  try {
    voucherAttachment.value = await uploadAttachment({
      applicationId: selectedPayout.value.applicationId,
      module: 'PAYOUT',
      file,
      remark: '出纳上传的打款凭证。',
    })
    ElMessage.success('凭证已上传。')
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    uploading.value = false
  }
}

function guardAmount() {
  if (Number(form.amount) > selectedPayoutAmount.value) {
    ElMessage.warning(`打款金额不能超过申请贷款金额 ${h5Money(selectedPayoutAmount.value)}。`)
    form.amount = selectedPayoutAmount.value
  }
}

function syncFormFromSelected() {
  if (!selectedPayout.value) {
    form.amount = 0
    form.paidAt = dateTimeValue()
    return
  }

  form.amount = Number(selectedPayout.value.amount ?? 0)
  form.paidAt = selectedPayout.value.paidAt ?? dateTimeValue()
  form.remark = selectedPayout.value.remark ?? '线下打款已完成，凭证已上传。'
}

function payoutCode(payout: PayoutRecord) {
  return payout.application?.applicationNo ?? payout.id
}

function payoutTitle(payout: PayoutRecord) {
  return payout.application?.model ?? '待打款订单'
}

function payoutSubtitle(payout: PayoutRecord) {
  const storeName = payout.application?.storeName ?? '未记录'
  const voucherText = payout.voucherAttachment || payout.voucher ? '已上传' : '待上传'

  return `商家：${storeName} / 凭证：${voucherText}`
}

function payoutStatusLabel(status: string | null | undefined) {
  if (status === 'PENDING') return '待打款'
  if (status === 'PAID') return '已打款'
  return '凭证异常'
}

function attachmentHref(attachment: AttachmentInfo) {
  const path = attachment.filePath

  if (!path) {
    return null
  }

  if (/^https?:\/\//.test(path) || path.startsWith('/')) {
    return path
  }

  return `/storage/${path}`
}

function dateTimeValue() {
  const date = new Date()
  const pad = (value: number) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`
}

function errorMessage(error: unknown) {
  if (typeof error === 'object' && error && 'response' in error) {
    return (
      (error as { response?: { data?: { error?: { message?: string } } } }).response?.data?.error
        ?.message ?? '操作失败，请稍后重试。'
    )
  }

  return '操作失败，请稍后重试。'
}

onMounted(() => {
  fetchPayouts()
})
</script>

<template>
  <H5AppFrame
    title="我的打款"
    :tabs="cashierTabs"
    :active-tab="activeTab"
    @tab-change="changeCashierTab"
  >
    <template v-if="activeTab === 'payouts'">
      <H5OverviewCard eyebrow="PAYOUT CENTER" title="打款中心" :stats="overviewStats">
        <template #action>
          <el-button class="refresh-button" :icon="Refresh" circle plain @click="fetchPayouts" />
        </template>
      </H5OverviewCard>

      <H5StatusTabs :tabs="payoutStatusTabs" :active="payoutFilter" @change="changePayoutFilter" />

      <div class="payout-list" :class="{ loading }">
        <el-skeleton v-if="loading" :rows="8" animated />
        <el-empty v-else-if="filteredPayouts.length === 0" description="暂无匹配打款" />
        <H5OrderCard
          v-for="payout in filteredPayouts"
          v-else
          :key="payout.id"
          class="clickable-card"
          :code="payoutCode(payout)"
          :title="payoutTitle(payout)"
          :subtitle="payoutSubtitle(payout)"
          :amount="`最高打款 ${h5Money(payout.amount)}`"
          :status="payoutStatusLabel(payout.status)"
          :image="h5ProductImage(payout.application?.model)"
          role="button"
          tabindex="0"
          @click="openPayoutDetail(payout)"
          @keydown.enter.prevent="openPayoutDetail(payout)"
        />
      </div>
    </template>

    <template v-else-if="activeTab === 'vouchers'">
      <H5OverviewCard
        eyebrow="VOUCHER LIST"
        title="凭证列表"
        :stats="[
          { label: '已上传', value: voucherPayouts.length },
          { label: '已打款', value: paidPayouts.length },
        ]"
      />

      <div class="payout-list">
        <el-empty v-if="voucherPayouts.length === 0" description="暂无打款凭证" />
        <H5OrderCard
          v-for="payout in voucherPayouts"
          v-else
          :key="payout.id"
          class="clickable-card"
          :code="payoutCode(payout)"
          :title="payoutTitle(payout)"
          :subtitle="payoutSubtitle(payout)"
          :amount="`打款 ${h5Money(payout.amount)}`"
          :status="payoutStatusLabel(payout.status)"
          :image="h5ProductImage(payout.application?.model)"
          role="button"
          tabindex="0"
          @click="openPayoutDetail(payout)"
          @keydown.enter.prevent="openPayoutDetail(payout)"
        >
          <el-button
            size="small"
            plain
            :disabled="!((payout.voucherAttachment ?? payout.voucher)?.filePath)"
            @click.stop="openPayoutDetail(payout)"
          >
            查看
          </el-button>
        </H5OrderCard>
      </div>
    </template>

    <template v-else>
      <section class="mine-stack">
        <H5OverviewCard eyebrow="CASHIER ACCOUNT" title="cashier001" :stats="mineStats" />

        <article class="profile-card">
          <div>
            <span>当前账号</span>
            <strong>cashier001</strong>
          </div>
          <div>
            <span>岗位角色</span>
            <strong>出纳</strong>
          </div>
          <p>处理待打款订单，上传并确认打款凭证。</p>
        </article>
      </section>
    </template>

    <input
      ref="voucherInput"
      class="hidden-file-input"
      type="file"
      accept=".png,.jpg,.jpeg,.webp,.pdf"
      @change="handleVoucherChange"
    />

    <H5DetailSheet :visible="detailVisible" title="确认打款" @close="detailVisible = false">
      <section v-if="selectedPayout" class="payout-detail">
        <div class="detail-hero">
          <img :src="h5ProductImage(selectedPayout.application?.model)" :alt="payoutTitle(selectedPayout)" />
          <div>
            <span>{{ payoutCode(selectedPayout) }}</span>
            <h2>{{ payoutTitle(selectedPayout) }}</h2>
            <strong>{{ payoutStatusLabel(selectedPayout.status) }}</strong>
          </div>
        </div>

        <dl class="detail-list">
          <div>
            <dt>申请编号</dt>
            <dd>{{ selectedPayout.application?.applicationNo ?? selectedPayout.applicationId ?? '-' }}</dd>
          </div>
          <div>
            <dt>客户</dt>
            <dd>{{ selectedPayout.application?.customerName ?? '-' }}</dd>
          </div>
          <div>
            <dt>商家</dt>
            <dd>{{ selectedPayout.application?.storeName ?? '未记录' }}</dd>
          </div>
          <div>
            <dt>最高打款金额</dt>
            <dd>{{ h5Money(selectedPayoutAmount) }}</dd>
          </div>
        </dl>

        <section class="form-card">
          <h3>打款信息</h3>
          <label>
            <span>确认金额</span>
            <el-input-number
              v-model="form.amount"
              :min="0"
              :max="selectedPayoutAmount"
              controls-position="right"
              @change="guardAmount"
            />
          </label>
          <p class="amount-limit">最高可打款：{{ h5Money(selectedPayoutAmount) }}</p>
          <label>
            <span>打款时间</span>
            <el-date-picker
              v-model="form.paidAt"
              type="datetime"
              value-format="YYYY-MM-DD HH:mm:ss"
              placeholder="选择打款时间"
            />
          </label>
          <label>
            <span>备注</span>
            <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="请输入备注" />
          </label>
        </section>

        <H5FileUploadBox
          label="打款凭证"
          description="支持 PNG、JPG、WEBP、PDF。上传后可立即预览。"
          :file-name="selectedVoucherFileName"
          :previewable="canPreviewVoucher"
          @upload="triggerVoucherUpload"
          @preview="previewVoucher"
        />

        <button
          v-if="selectedVoucherFileName && canPreviewVoucher"
          class="preview-link"
          type="button"
          @click="previewVoucher"
        >
          预览凭证
        </button>
      </section>

      <template #footer>
        <div class="sheet-actions">
          <el-button plain @click="detailVisible = false">关闭</el-button>
          <el-button
            type="primary"
            :icon="Money"
            :disabled="!canConfirmSelected || uploading"
            :loading="operating"
            @click="confirmSelectedPayout"
          >
            确认打款
          </el-button>
        </div>
      </template>
    </H5DetailSheet>
  </H5AppFrame>
</template>

<style scoped>
.refresh-button {
  --el-button-bg-color: var(--h5-soft);
  --el-button-border-color: var(--h5-border);
  --el-button-hover-bg-color: #fff;
  --el-button-hover-border-color: var(--h5-blue);
  --el-button-hover-text-color: var(--h5-blue);
}

.payout-list,
.mine-stack {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.payout-list.loading {
  padding: 4px 0;
}

.clickable-card {
  cursor: pointer;
  transition:
    transform 0.16s ease,
    box-shadow 0.16s ease;
}

.clickable-card:focus-visible,
.clickable-card:hover {
  transform: translateY(-1px);
  outline: 2px solid rgba(93, 120, 255, 0.34);
  outline-offset: 2px;
  box-shadow: 0 18px 36px rgba(61, 86, 150, 0.16);
}

.hidden-file-input {
  position: fixed;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

.profile-card,
.payout-detail,
.form-card {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.profile-card,
.form-card,
.detail-list {
  padding: 18px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.profile-card div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  padding: 12px 0;
  border-bottom: 1px solid var(--h5-border);
}

.profile-card div:last-of-type {
  border-bottom: 0;
}

.profile-card span,
.profile-card p {
  color: var(--h5-muted);
  font-size: 13px;
  line-height: 1.5;
}

.profile-card strong {
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-size: 15px;
  font-weight: 900;
  line-height: 1.35;
}

.profile-card p {
  margin: 4px 0 0;
  padding-top: 12px;
  border-top: 1px solid var(--h5-border);
}

.detail-hero {
  display: grid;
  grid-template-columns: 86px minmax(0, 1fr);
  gap: 14px;
  align-items: center;
  padding: 16px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: linear-gradient(135deg, #fff, var(--h5-soft));
}

.detail-hero img {
  width: 86px;
  height: 104px;
  border-radius: 18px;
  background: #fff;
  object-fit: contain;
}

.detail-hero div {
  display: grid;
  min-width: 0;
  gap: 7px;
}

.detail-hero span {
  overflow: hidden;
  color: var(--h5-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.detail-hero h2 {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-size: 20px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.25;
}

.detail-hero strong {
  width: fit-content;
  padding: 5px 10px;
  border-radius: 999px;
  background: rgba(93, 120, 255, 0.12);
  color: var(--h5-blue);
  font-size: 12px;
  line-height: 1.2;
}

.detail-list {
  display: grid;
  gap: 0;
  margin: 0;
  box-shadow: none;
}

.detail-list div {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr);
  gap: 12px;
  padding: 11px 0;
  border-bottom: 1px solid var(--h5-border);
}

.detail-list div:last-child {
  border-bottom: 0;
}

.detail-list dt,
.detail-list dd {
  min-width: 0;
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
}

.detail-list dt {
  color: var(--h5-muted);
  font-weight: 800;
}

.detail-list dd {
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-weight: 900;
}

.form-card {
  box-shadow: none;
}

.form-card h3 {
  margin: 0;
  color: var(--h5-ink);
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.35;
}

.form-card label {
  display: grid;
  min-width: 0;
  gap: 8px;
}

.form-card label span,
.amount-limit {
  color: var(--h5-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.35;
}

.amount-limit {
  margin: -6px 0 2px;
  color: var(--h5-blue);
}

.form-card :deep(.el-input-number),
.form-card :deep(.el-date-editor.el-input),
.form-card :deep(.el-textarea) {
  width: 100%;
}

.preview-link {
  justify-self: start;
  min-height: 36px;
  padding: 0 16px;
  border: 1px solid var(--h5-blue);
  border-radius: 999px;
  background: var(--h5-soft);
  color: var(--h5-blue);
  font-size: 13px;
  font-weight: 900;
  cursor: pointer;
}

.sheet-actions {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
  gap: 10px;
  width: 100%;
}

.sheet-actions .el-button {
  width: 100%;
  margin-left: 0;
}

@media (max-width: 390px) {
  .detail-hero,
  .detail-list div {
    grid-template-columns: 1fr;
  }

  .detail-hero img {
    justify-self: center;
  }
}
</style>
