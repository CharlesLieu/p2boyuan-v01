<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import H5AppFrame from '../../components/h5/H5AppFrame.vue'
import H5DetailSheet from '../../components/h5/H5DetailSheet.vue'
import H5OrderCard from '../../components/h5/H5OrderCard.vue'
import H5OverviewCard from '../../components/h5/H5OverviewCard.vue'
import H5StatusTabs from '../../components/h5/H5StatusTabs.vue'
import {
  getMerchantMe,
  getMerchantVoucher,
  listMerchantVouchers,
  submitMerchantOnboarding,
  type MerchantOnboarding,
  type MerchantOnboardingPayload,
  type MerchantOnboardingStatus,
  type MerchantProfile,
  type MerchantVoucher,
  type MerchantVoucherStatus,
} from '../../api/modules/merchant'
import { useAuthStore } from '../../stores/auth'
import {
  h5AttachmentHref,
  h5Money,
  h5ProductImage,
  h5VoucherStatusLabel,
} from '../../utils/h5Format'

type StoreTab = 'onboarding' | 'vouchers' | 'mine'
type VoucherFilter = MerchantVoucherStatus | 'ALL'

const auth = useAuthStore()
const activeTab = ref<StoreTab>('vouchers')
const voucherFilter = ref<VoucherFilter>('ALL')
const loading = ref(false)
const operating = ref(false)
const profile = ref<MerchantProfile | null>(null)
const latestOnboarding = ref<MerchantOnboarding | null>(null)
const vouchers = ref<MerchantVoucher[]>([])
const selectedVoucher = ref<MerchantVoucher | null>(null)
const voucherDetailVisible = ref(false)

const storeTabs = [
  { key: 'onboarding', label: '入驻' },
  { key: 'vouchers', label: '凭证' },
  { key: 'mine', label: '我的' },
]
const voucherTabs: Array<{ key: VoucherFilter; label: string }> = [
  { key: 'ALL', label: '全部' },
  { key: 'PENDING_CONFIRMATION', label: '待确认' },
  { key: 'PAID', label: '已打款' },
  { key: 'VOIDED', label: '已作废' },
]
const onboardingSteps: Array<{ key: MerchantOnboardingStatus; label: string }> = [
  { key: 'DRAFT', label: '未提交资料' },
  { key: 'PENDING_REVIEW', label: '平台审核中' },
  { key: 'APPROVED', label: '已通过' },
  { key: 'REJECTED', label: '已驳回' },
]

const onboardingForm = reactive<MerchantOnboardingPayload>({
  applicantName: auth.user?.display_name ?? '',
  applicantPhone: '',
  applicantIdNumber: '',
  merchantName: auth.user?.display_name ?? '',
  merchantAddress: '',
  contactName: auth.user?.display_name ?? '',
  contactPhone: '',
  paymentMethod: 'BANK',
  paymentAccount: '',
  paymentAccountName: auth.user?.display_name ?? '',
  paymentBankOrChannel: '',
  idCardFrontFile: {
    fileName: 'id-card-front.png',
    filePath: 'merchant/id-card-front.png',
    mimeType: 'image/png',
    fileSize: 120000,
  },
  idCardBackFile: {
    fileName: 'id-card-back.png',
    filePath: 'merchant/id-card-back.png',
    mimeType: 'image/png',
    fileSize: 120000,
  },
  qualificationFile: {
    fileName: 'merchant-license.pdf',
    filePath: 'merchant/merchant-license.pdf',
    mimeType: 'application/pdf',
    fileSize: 240000,
  },
})

const pageTitle = computed(() =>
  activeTab.value === 'onboarding'
    ? '商家入驻'
    : activeTab.value === 'mine'
      ? '我的商家'
      : '我的凭证',
)
const onboardingStatus = computed(
  () => profile.value?.onboardingStatus ?? latestOnboarding.value?.status ?? 'DRAFT',
)
const isApproved = computed(() => onboardingStatus.value === 'APPROVED')
const canSubmitOnboarding = computed(
  () => onboardingStatus.value === 'DRAFT' || onboardingStatus.value === 'REJECTED',
)
const filteredVouchers = computed(() => {
  if (voucherFilter.value === 'ALL') {
    return vouchers.value
  }

  return vouchers.value.filter((voucher) => voucher.status === voucherFilter.value)
})
const paidTotal = computed(() =>
  vouchers.value
    .filter((voucher) => voucher.status === 'PAID')
    .reduce((sum, voucher) => sum + Number(voucher.amount || 0), 0),
)
const onboardingStats = computed(() => [
  { label: '入驻状态', value: onboardingStatusLabel(onboardingStatus.value) },
  { label: '凭证数量', value: vouchers.value.length },
])
const voucherStats = computed(() => [
  { label: '凭证总数', value: vouchers.value.length },
  { label: '已打款金额', value: h5Money(paidTotal.value) },
])
const mineStats = computed(() => [
  { label: '入驻状态', value: onboardingStatusLabel(onboardingStatus.value) },
  { label: '商家编号', value: profile.value?.storeCode ?? '-' },
])
const onboardingSubmitText = computed(() => {
  if (onboardingStatus.value === 'PENDING_REVIEW') return '已提交审核'
  if (onboardingStatus.value === 'APPROVED') return '已通过审核'
  return '提交入驻申请'
})

async function refresh() {
  loading.value = true

  try {
    const merchant = await getMerchantMe()
    profile.value = merchant.profile
    latestOnboarding.value = merchant.latestOnboarding

    syncFormFromMerchant()

    if (merchant.profile.onboardingStatus === 'APPROVED') {
      vouchers.value = await listMerchantVouchers()
      activeTab.value = activeTab.value === 'onboarding' ? 'vouchers' : activeTab.value
    } else {
      vouchers.value = []
      selectedVoucher.value = null
      voucherDetailVisible.value = false
      activeTab.value = 'onboarding'
    }
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function submitOnboarding() {
  if (!canSubmitOnboarding.value) {
    ElMessage.warning('当前入驻状态不允许重复提交。')
    return
  }

  if (!validateOnboardingForm()) {
    ElMessage.warning('请先补全商家入驻资料。')
    return
  }

  operating.value = true

  try {
    latestOnboarding.value = await submitMerchantOnboarding({ ...onboardingForm })
    ElMessage.success('入驻申请已提交，等待平台审核。')
    await refresh()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

async function openVoucher(voucher: MerchantVoucher) {
  try {
    selectedVoucher.value = await getMerchantVoucher(voucher.id)
    voucherDetailVisible.value = true
  } catch (error) {
    ElMessage.error(errorMessage(error))
  }
}

function changeStoreTab(key: string) {
  if (key === 'onboarding' || key === 'vouchers' || key === 'mine') {
    activeTab.value = key
  }
}

function changeVoucherFilter(key: string) {
  if (voucherTabs.some((tab) => tab.key === key)) {
    voucherFilter.value = key as VoucherFilter
  }
}

function previewFile(filePath?: string | null) {
  const href = h5AttachmentHref(filePath)
  if (href) window.open(href, '_blank', 'noopener,noreferrer')
}

function validateOnboardingForm() {
  return [
    onboardingForm.applicantName,
    onboardingForm.applicantPhone,
    onboardingForm.applicantIdNumber,
    onboardingForm.merchantName,
    onboardingForm.merchantAddress,
    onboardingForm.contactName,
    onboardingForm.contactPhone,
    onboardingForm.paymentMethod,
    onboardingForm.paymentAccount,
    onboardingForm.paymentAccountName,
  ].every((value) => String(value ?? '').trim().length > 0)
}

function onboardingStatusLabel(status: MerchantOnboardingStatus | string | null | undefined) {
  const labels: Record<string, string> = {
    DRAFT: '未提交资料',
    PENDING_REVIEW: '平台审核中',
    APPROVED: '已通过',
    REJECTED: '已驳回',
    DISABLED: '已停用',
  }

  return labels[String(status ?? '')] ?? String(status ?? '未知')
}

function stepState(step: MerchantOnboardingStatus) {
  const status = onboardingStatus.value

  if (step === status) return 'active'
  if (status === 'APPROVED' && ['DRAFT', 'PENDING_REVIEW'].includes(step)) return 'done'
  if (status === 'PENDING_REVIEW' && step === 'DRAFT') return 'done'
  if (status === 'REJECTED' && ['DRAFT', 'PENDING_REVIEW'].includes(step)) return 'done'

  return 'idle'
}

function syncFormFromMerchant() {
  const source = latestOnboarding.value
  const merchant = profile.value

  onboardingForm.applicantName = source?.applicantName ?? merchant?.contactName ?? onboardingForm.applicantName
  onboardingForm.applicantPhone = source?.applicantPhone ?? merchant?.contactPhone ?? onboardingForm.applicantPhone
  onboardingForm.merchantName = source?.merchantName ?? merchant?.name ?? onboardingForm.merchantName
  onboardingForm.merchantAddress = source?.merchantAddress ?? merchant?.address ?? onboardingForm.merchantAddress
  onboardingForm.contactName = source?.contactName ?? merchant?.contactName ?? onboardingForm.contactName
  onboardingForm.contactPhone = source?.contactPhone ?? merchant?.contactPhone ?? onboardingForm.contactPhone
  onboardingForm.paymentMethod = source?.paymentMethod ?? merchant?.paymentMethod ?? onboardingForm.paymentMethod
  onboardingForm.paymentAccountName =
    source?.paymentAccountName ?? merchant?.paymentAccountName ?? onboardingForm.paymentAccountName
  onboardingForm.paymentBankOrChannel =
    source?.paymentBankOrChannel ??
    merchant?.paymentBankOrChannel ??
    onboardingForm.paymentBankOrChannel
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
  refresh()
})
</script>

<template>
  <H5AppFrame
    v-loading="loading"
    :title="pageTitle"
    :tabs="storeTabs"
    :active-tab="activeTab"
    @tab-change="changeStoreTab"
  >
    <template v-if="activeTab === 'onboarding'">
      <section class="store-stack">
        <H5OverviewCard eyebrow="MERCHANT CENTER" title="商家中心" :stats="onboardingStats">
          <template #action>
            <el-button class="refresh-button" :icon="Refresh" circle plain @click="refresh" />
          </template>
        </H5OverviewCard>

        <article class="status-card">
          <div class="status-card-head">
            <span>当前状态</span>
            <strong>{{ onboardingStatusLabel(onboardingStatus) }}</strong>
          </div>
          <p v-if="latestOnboarding?.rejectReason" class="reject-reason">
            驳回原因：{{ latestOnboarding.rejectReason }}
          </p>
          <p v-else-if="isApproved">商家入驻已通过，可以查看本商家的打款凭证。</p>
          <p v-else>提交资料后，平台会完成商家准入审核。</p>
        </article>

        <article class="onboarding-steps" aria-label="入驻审核流程">
          <div
            v-for="step in onboardingSteps"
            :key="step.key"
            class="step-item"
            :class="stepState(step.key)"
          >
            <span>{{ step.label }}</span>
          </div>
        </article>

        <section class="form-card">
          <div class="section-title">
            <h2>入驻资料</h2>
            <span>{{ onboardingStatusLabel(onboardingStatus) }}</span>
          </div>

          <el-form label-position="top" class="merchant-form">
            <el-form-item label="申请人姓名">
              <el-input v-model="onboardingForm.applicantName" placeholder="请输入申请人姓名" />
            </el-form-item>
            <el-form-item label="申请人手机号">
              <el-input v-model="onboardingForm.applicantPhone" placeholder="请输入申请人手机号" />
            </el-form-item>
            <el-form-item label="证件号码">
              <el-input v-model="onboardingForm.applicantIdNumber" placeholder="请输入证件号码" />
            </el-form-item>
            <el-form-item label="商家名称">
              <el-input v-model="onboardingForm.merchantName" placeholder="请输入商家名称" />
            </el-form-item>
            <el-form-item label="商家地址">
              <el-input v-model="onboardingForm.merchantAddress" placeholder="请输入商家地址" />
            </el-form-item>
            <el-form-item label="联系人">
              <el-input v-model="onboardingForm.contactName" placeholder="请输入联系人" />
            </el-form-item>
            <el-form-item label="联系电话">
              <el-input v-model="onboardingForm.contactPhone" placeholder="请输入联系电话" />
            </el-form-item>
            <el-form-item label="收款方式">
              <el-input v-model="onboardingForm.paymentMethod" placeholder="请输入收款方式" />
            </el-form-item>
            <el-form-item label="收款账号">
              <el-input v-model="onboardingForm.paymentAccount" placeholder="请输入收款账号" />
            </el-form-item>
            <el-form-item label="收款户名">
              <el-input v-model="onboardingForm.paymentAccountName" placeholder="请输入收款户名" />
            </el-form-item>
            <el-form-item label="开户行或收款渠道">
              <el-input
                v-model="onboardingForm.paymentBankOrChannel"
                placeholder="请输入开户行或收款渠道"
              />
            </el-form-item>

            <div class="file-grid">
              <article>
                <span>身份证正面</span>
                <strong>资料文件将在正式上传接口接入后上传</strong>
              </article>
              <article>
                <span>身份证反面</span>
                <strong>资料文件将在正式上传接口接入后上传</strong>
              </article>
              <article>
                <span>商家资质</span>
                <strong>资料文件将在正式上传接口接入后上传</strong>
              </article>
            </div>

            <el-button
              class="submit-button"
              type="primary"
              size="large"
              :loading="operating"
              :disabled="!canSubmitOnboarding"
              @click="submitOnboarding"
            >
              {{ onboardingSubmitText }}
            </el-button>
          </el-form>
        </section>
      </section>
    </template>

    <template v-else-if="activeTab === 'vouchers'">
      <section class="store-stack">
        <H5OverviewCard eyebrow="VOUCHER CENTER" title="打款凭证" :stats="voucherStats">
          <template #action>
            <el-button class="refresh-button" :icon="Refresh" circle plain @click="refresh" />
          </template>
        </H5OverviewCard>

        <H5StatusTabs :tabs="voucherTabs" :active="voucherFilter" @change="changeVoucherFilter" />

        <div class="voucher-list" :class="{ loading }">
          <el-skeleton v-if="loading" :rows="8" animated />
          <el-empty v-else-if="!isApproved" description="入驻审核通过后可查看打款凭证" />
          <el-empty v-else-if="filteredVouchers.length === 0" description="暂无打款凭证" />
          <H5OrderCard
            v-for="voucher in filteredVouchers"
            v-else
            :key="voucher.id"
            class="clickable-card"
            :code="voucher.voucherNo"
            :title="voucher.relatedBusinessNo || '打款凭证'"
            :subtitle="`商家：${voucher.storeName ?? profile?.name ?? '本商家'}`"
            :amount="h5Money(voucher.amount)"
            :status="h5VoucherStatusLabel(voucher.status)"
            :image="h5ProductImage(voucher.relatedBusinessNo)"
            role="button"
            tabindex="0"
            @click="openVoucher(voucher)"
            @keydown.enter.prevent="openVoucher(voucher)"
          >
            <el-button size="small" plain @click.stop="openVoucher(voucher)">查看</el-button>
          </H5OrderCard>
        </div>
      </section>
    </template>

    <template v-else>
      <section class="store-stack">
        <H5OverviewCard
          eyebrow="MERCHANT PROFILE"
          :title="profile?.name || auth.user?.display_name || '我的商家'"
          :stats="mineStats"
        >
          <template #action>
            <el-button class="refresh-button" :icon="Refresh" circle plain @click="refresh" />
          </template>
        </H5OverviewCard>

        <article class="profile-card">
          <dl class="info-list">
            <div>
              <dt>商家名称</dt>
              <dd>{{ profile?.name || '-' }}</dd>
            </div>
            <div>
              <dt>地址</dt>
              <dd>{{ profile?.address || '-' }}</dd>
            </div>
            <div>
              <dt>联系人</dt>
              <dd>{{ profile?.contactName || '-' }}</dd>
            </div>
            <div>
              <dt>电话</dt>
              <dd>{{ profile?.contactPhone || '-' }}</dd>
            </div>
            <div>
              <dt>收款方式</dt>
              <dd>{{ profile?.paymentMethod || '-' }}</dd>
            </div>
            <div>
              <dt>收款账号</dt>
              <dd>{{ profile?.paymentAccountMasked || '-' }}</dd>
            </div>
            <div>
              <dt>收款户名</dt>
              <dd>{{ profile?.paymentAccountName || '-' }}</dd>
            </div>
            <div>
              <dt>入驻状态</dt>
              <dd>{{ onboardingStatusLabel(onboardingStatus) }}</dd>
            </div>
          </dl>
        </article>
      </section>
    </template>

    <H5DetailSheet
      :visible="voucherDetailVisible"
      title="凭证详情"
      @close="voucherDetailVisible = false"
    >
      <section v-if="selectedVoucher" class="voucher-detail">
        <div class="detail-hero">
          <img
            :src="h5ProductImage(selectedVoucher.relatedBusinessNo)"
            :alt="selectedVoucher.relatedBusinessNo || selectedVoucher.voucherNo"
          />
          <div>
            <span>{{ selectedVoucher.voucherNo }}</span>
            <h2>{{ h5Money(selectedVoucher.amount) }}</h2>
            <strong>{{ h5VoucherStatusLabel(selectedVoucher.status) }}</strong>
          </div>
        </div>

        <dl class="info-list detail-list">
          <div>
            <dt>凭证号</dt>
            <dd>{{ selectedVoucher.voucherNo }}</dd>
          </div>
          <div>
            <dt>金额</dt>
            <dd>{{ h5Money(selectedVoucher.amount) }}</dd>
          </div>
          <div>
            <dt>状态</dt>
            <dd>{{ h5VoucherStatusLabel(selectedVoucher.status) }}</dd>
          </div>
          <div>
            <dt>商家</dt>
            <dd>{{ selectedVoucher.storeName || profile?.name || '-' }}</dd>
          </div>
          <div>
            <dt>打款时间</dt>
            <dd>{{ selectedVoucher.paidAt || '-' }}</dd>
          </div>
          <div>
            <dt>收款主体</dt>
            <dd>{{ selectedVoucher.payeeName || '-' }}</dd>
          </div>
          <div>
            <dt>收款账号</dt>
            <dd>{{ selectedVoucher.payeeAccountMasked || '-' }}</dd>
          </div>
          <div>
            <dt>付款方</dt>
            <dd>{{ selectedVoucher.payerName || '-' }}</dd>
          </div>
          <div>
            <dt>业务编号</dt>
            <dd>{{ selectedVoucher.relatedBusinessNo || '-' }}</dd>
          </div>
          <div>
            <dt>备注</dt>
            <dd>{{ selectedVoucher.remark || '-' }}</dd>
          </div>
          <div v-if="selectedVoucher.voidReason">
            <dt>作废原因</dt>
            <dd>{{ selectedVoucher.voidReason }}</dd>
          </div>
        </dl>
      </section>

      <template #footer>
        <div class="sheet-actions">
          <el-button plain @click="voucherDetailVisible = false">关闭</el-button>
          <el-button
            type="primary"
            :disabled="!selectedVoucher?.voucherFile?.filePath"
            @click="previewFile(selectedVoucher?.voucherFile?.filePath)"
          >
            预览凭证
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

.store-stack,
.voucher-list,
.voucher-detail {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.voucher-list.loading {
  padding: 4px 0;
}

.status-card,
.onboarding-steps,
.form-card,
.profile-card,
.detail-list,
.detail-hero {
  min-width: 0;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.status-card,
.form-card,
.profile-card,
.detail-list,
.detail-hero {
  padding: 18px;
}

.status-card {
  display: grid;
  gap: 10px;
}

.status-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.status-card span,
.status-card p,
.section-title span,
.file-grid span {
  color: var(--h5-muted);
  font-size: 13px;
  line-height: 1.5;
}

.status-card strong {
  color: var(--h5-blue);
  font-size: 18px;
  font-weight: 900;
  line-height: 1.2;
}

.status-card p {
  margin: 0;
}

.reject-reason {
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(240, 122, 74, 0.1);
  color: var(--h5-orange) !important;
  font-weight: 700;
}

.onboarding-steps {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 10px;
}

.step-item {
  display: grid;
  place-items: center;
  min-width: 0;
  min-height: 46px;
  padding: 8px;
  border-radius: 14px;
  background: var(--h5-soft);
  color: var(--h5-muted);
  text-align: center;
}

.step-item span {
  overflow-wrap: anywhere;
  font-size: 12px;
  font-weight: 800;
  line-height: 1.25;
}

.step-item.done {
  color: var(--h5-blue);
}

.step-item.active {
  background: var(--h5-blue);
  color: #fff;
  box-shadow: 0 8px 18px rgba(93, 120, 255, 0.25);
}

.section-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.section-title h2 {
  margin: 0;
  color: var(--h5-ink);
  font-size: 17px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.35;
}

.section-title h2::after {
  display: block;
  width: 32px;
  height: 3px;
  margin-top: 8px;
  border-radius: 999px;
  background: var(--h5-orange);
  content: "";
}

.merchant-form :deep(.el-form-item) {
  margin-bottom: 14px;
}

.merchant-form :deep(.el-form-item__label) {
  color: var(--h5-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.35;
}

.merchant-form :deep(.el-input__wrapper) {
  min-height: 46px;
  border-radius: 14px;
  background: var(--h5-soft);
  box-shadow: 0 0 0 1px var(--h5-border) inset;
}

.merchant-form :deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 1px var(--h5-blue) inset;
}

.file-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 2px 0 16px;
}

.file-grid article {
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--h5-border);
  border-radius: 16px;
  background: var(--h5-soft);
}

.file-grid span,
.file-grid strong {
  display: block;
}

.file-grid strong {
  margin-top: 7px;
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-size: 13px;
  font-weight: 900;
  line-height: 1.35;
}

.submit-button {
  width: 100%;
  min-height: 46px;
  border-radius: 999px;
  font-weight: 900;
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

.info-list {
  display: grid;
  gap: 0;
  margin: 0;
}

.info-list div {
  display: grid;
  grid-template-columns: 98px minmax(0, 1fr);
  gap: 12px;
  min-width: 0;
  padding: 13px 0;
  border-bottom: 1px solid var(--h5-border);
}

.info-list div:first-child {
  padding-top: 0;
}

.info-list div:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.info-list dt,
.info-list dd {
  min-width: 0;
  margin: 0;
  line-height: 1.45;
}

.info-list dt {
  color: var(--h5-muted);
  font-size: 13px;
  font-weight: 700;
}

.info-list dd {
  overflow-wrap: anywhere;
  color: var(--h5-ink);
  font-size: 14px;
  font-weight: 900;
}

.detail-hero {
  display: grid;
  grid-template-columns: 86px minmax(0, 1fr);
  gap: 14px;
  align-items: center;
  background: linear-gradient(135deg, #fff, var(--h5-soft));
}

.detail-hero img {
  width: 86px;
  height: 96px;
  border-radius: 18px;
  background: var(--h5-soft);
  object-fit: contain;
}

.detail-hero span,
.detail-hero h2 {
  overflow-wrap: anywhere;
}

.detail-hero span {
  color: var(--h5-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.35;
}

.detail-hero h2 {
  margin: 7px 0;
  color: var(--h5-ink);
  font-size: 24px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.2;
}

.detail-hero strong {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  background: rgba(240, 122, 74, 0.12);
  color: var(--h5-orange);
  font-size: 12px;
  line-height: 1.2;
}

.sheet-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  width: 100%;
}

@media (max-width: 520px) {
  .onboarding-steps,
  .file-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .info-list div {
    grid-template-columns: 86px minmax(0, 1fr);
  }
}
</style>
