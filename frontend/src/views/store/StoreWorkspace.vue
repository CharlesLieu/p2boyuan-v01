<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Document, Refresh, Shop, User } from '@element-plus/icons-vue'
import {
  getMerchantMe,
  getMerchantVoucher,
  listMerchantVouchers,
  submitMerchantOnboarding,
  type MerchantOnboarding,
  type MerchantOnboardingPayload,
  type MerchantProfile,
  type MerchantVoucher,
  type MerchantVoucherStatus,
} from '../../api/modules/merchant'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const activeTab = ref<'onboarding' | 'vouchers' | 'mine'>('vouchers')
const voucherFilter = ref<MerchantVoucherStatus | 'ALL'>('ALL')
const loading = ref(false)
const operating = ref(false)
const profile = ref<MerchantProfile | null>(null)
const latestOnboarding = ref<MerchantOnboarding | null>(null)
const vouchers = ref<MerchantVoucher[]>([])
const selectedVoucher = ref<MerchantVoucher | null>(null)
const voucherDetailVisible = ref(false)

const onboardingForm = reactive<MerchantOnboardingPayload>({
  applicantName: '测试店长',
  applicantPhone: '0900-STORE-001',
  applicantIdNumber: 'MERCHANT-ID-NEW',
  merchantName: auth.user?.display_name ?? '测试商家',
  merchantAddress: '测试商家地址',
  contactName: '测试店长',
  contactPhone: '0900-STORE-001',
  paymentMethod: 'BANK',
  paymentAccount: '6222000099998888',
  paymentAccountName: auth.user?.display_name ?? '测试商家',
  paymentBankOrChannel: '测试银行',
  idCardFrontFile: {
    fileName: 'id-card-front-demo.png',
    filePath: 'demo/merchant/id-card-front-demo.png',
    mimeType: 'image/png',
    fileSize: 120000,
  },
  idCardBackFile: {
    fileName: 'id-card-back-demo.png',
    filePath: 'demo/merchant/id-card-back-demo.png',
    mimeType: 'image/png',
    fileSize: 120000,
  },
  qualificationFile: {
    fileName: 'merchant-license-demo.pdf',
    filePath: 'demo/merchant/merchant-license-demo.pdf',
    mimeType: 'application/pdf',
    fileSize: 240000,
  },
})

const onboardingStatus = computed(() => profile.value?.onboardingStatus ?? latestOnboarding.value?.status ?? 'DRAFT')
const isApproved = computed(() => onboardingStatus.value === 'APPROVED')
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

async function refresh() {
  loading.value = true

  try {
    const merchant = await getMerchantMe()
    profile.value = merchant.profile
    latestOnboarding.value = merchant.latestOnboarding

    if (merchant.profile.onboardingStatus === 'APPROVED') {
      vouchers.value = await listMerchantVouchers()
      activeTab.value = activeTab.value === 'onboarding' ? 'vouchers' : activeTab.value
    } else {
      vouchers.value = []
      activeTab.value = 'onboarding'
    }
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function submitOnboarding() {
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

function money(value: number | string | null | undefined) {
  return `¥ ${Number(value ?? 0).toLocaleString('zh-CN')}`
}

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    DRAFT: '待提交',
    PENDING_REVIEW: '审核中',
    APPROVED: '审核通过',
    REJECTED: '已驳回',
    DISABLED: '已停用',
    PENDING_CONFIRMATION: '待确认',
    PAID: '已打款',
    VOIDED: '已作废',
  }

  return labels[status] ?? status
}

function previewFile(filePath?: string | null) {
  if (!filePath) {
    return
  }

  window.open(filePath.startsWith('/') ? filePath : `/${filePath}`, '_blank', 'noopener,noreferrer')
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
  <section class="merchant-h5" v-loading="loading">
    <header class="merchant-hero">
      <div>
        <span class="soft-chip">商家端</span>
        <h2>{{ profile?.name || auth.user?.display_name || '商家' }}</h2>
        <p>查看入驻状态和公司提供给本商家的打款凭证。</p>
      </div>
      <el-button :icon="Refresh" circle plain @click="refresh" />
    </header>

    <div class="merchant-status-card">
      <span>入驻状态</span>
      <strong>{{ statusLabel(onboardingStatus) }}</strong>
      <p v-if="latestOnboarding?.rejectReason">{{ latestOnboarding.rejectReason }}</p>
      <p v-else-if="isApproved">已通过平台审核，可以查看本商家的打款凭证。</p>
      <p v-else>提交资料后，平台会在后台完成商家准入审核。</p>
    </div>

    <main class="merchant-main">
      <section v-if="activeTab === 'onboarding'" class="h5-panel">
        <div class="panel-heading">
          <h3>商家入驻申请</h3>
          <el-tag :type="onboardingStatus === 'APPROVED' ? 'success' : onboardingStatus === 'REJECTED' ? 'danger' : 'warning'">
            {{ statusLabel(onboardingStatus) }}
          </el-tag>
        </div>

        <el-alert
          v-if="onboardingStatus === 'APPROVED'"
          type="success"
          title="商家入驻已通过。资料变更会重新进入审核。"
          show-icon
        />

        <el-form label-position="top" class="merchant-form">
          <el-form-item label="申请人姓名"><el-input v-model="onboardingForm.applicantName" /></el-form-item>
          <el-form-item label="申请人手机号"><el-input v-model="onboardingForm.applicantPhone" /></el-form-item>
          <el-form-item label="证件号码"><el-input v-model="onboardingForm.applicantIdNumber" /></el-form-item>
          <el-form-item label="商家名称"><el-input v-model="onboardingForm.merchantName" /></el-form-item>
          <el-form-item label="商家地址"><el-input v-model="onboardingForm.merchantAddress" /></el-form-item>
          <el-form-item label="联系人"><el-input v-model="onboardingForm.contactName" /></el-form-item>
          <el-form-item label="联系电话"><el-input v-model="onboardingForm.contactPhone" /></el-form-item>
          <el-form-item label="收款方式"><el-input v-model="onboardingForm.paymentMethod" /></el-form-item>
          <el-form-item label="收款账号"><el-input v-model="onboardingForm.paymentAccount" /></el-form-item>
          <el-form-item label="收款户名"><el-input v-model="onboardingForm.paymentAccountName" /></el-form-item>
          <el-form-item label="开户行或收款渠道"><el-input v-model="onboardingForm.paymentBankOrChannel" /></el-form-item>
          <div class="file-grid">
            <article>
              <span>身份证正面</span>
              <strong>{{ onboardingForm.idCardFrontFile.fileName }}</strong>
            </article>
            <article>
              <span>身份证反面</span>
              <strong>{{ onboardingForm.idCardBackFile.fileName }}</strong>
            </article>
            <article>
              <span>商家资质</span>
              <strong>{{ onboardingForm.qualificationFile.fileName }}</strong>
            </article>
          </div>
          <el-button type="primary" size="large" :loading="operating" @click="submitOnboarding">
            提交入驻申请
          </el-button>
        </el-form>
      </section>

      <section v-if="activeTab === 'vouchers'" class="h5-panel">
        <div class="panel-heading">
          <h3>打款凭证</h3>
          <el-tag type="success">{{ filteredVouchers.length }} 笔</el-tag>
        </div>

        <div class="merchant-metrics">
          <article><span>凭证总数</span><strong>{{ vouchers.length }}</strong></article>
          <article><span>已打款金额</span><strong>{{ money(paidTotal) }}</strong></article>
        </div>

        <div class="status-tabs">
          <button :class="{ active: voucherFilter === 'ALL' }" @click="voucherFilter = 'ALL'">全部</button>
          <button :class="{ active: voucherFilter === 'PENDING_CONFIRMATION' }" @click="voucherFilter = 'PENDING_CONFIRMATION'">
            待确认
          </button>
          <button :class="{ active: voucherFilter === 'PAID' }" @click="voucherFilter = 'PAID'">已打款</button>
          <button :class="{ active: voucherFilter === 'VOIDED' }" @click="voucherFilter = 'VOIDED'">已作废</button>
        </div>

        <el-empty v-if="!isApproved" description="入驻审核通过后可查看打款凭证" />
        <el-empty v-else-if="filteredVouchers.length === 0" description="暂无打款凭证" />
        <div v-else class="voucher-list">
          <article
            v-for="voucher in filteredVouchers"
            :key="voucher.id"
            class="voucher-card"
            @click="openVoucher(voucher)"
          >
            <div>
              <span>{{ voucher.voucherNo }}</span>
              <strong>{{ money(voucher.amount) }}</strong>
              <p>{{ voucher.relatedBusinessNo || '未关联业务编号' }}</p>
            </div>
            <el-tag :type="voucher.status === 'PAID' ? 'success' : voucher.status === 'VOIDED' ? 'info' : 'warning'">
              {{ statusLabel(voucher.status) }}
            </el-tag>
          </article>
        </div>
      </section>

      <section v-if="activeTab === 'mine'" class="h5-panel">
        <div class="panel-heading">
          <h3>我的商家信息</h3>
          <el-tag>{{ profile?.storeCode }}</el-tag>
        </div>
        <dl class="merchant-info">
          <dt>商家名称</dt><dd>{{ profile?.name || '-' }}</dd>
          <dt>地址</dt><dd>{{ profile?.address || '-' }}</dd>
          <dt>联系人</dt><dd>{{ profile?.contactName || '-' }}</dd>
          <dt>联系电话</dt><dd>{{ profile?.contactPhone || '-' }}</dd>
          <dt>收款方式</dt><dd>{{ profile?.paymentMethod || '-' }}</dd>
          <dt>收款账号</dt><dd>{{ profile?.paymentAccountMasked || '-' }}</dd>
          <dt>收款户名</dt><dd>{{ profile?.paymentAccountName || '-' }}</dd>
          <dt>入驻状态</dt><dd>{{ statusLabel(onboardingStatus) }}</dd>
        </dl>
      </section>
    </main>

    <nav class="merchant-tabbar">
      <button :class="{ active: activeTab === 'onboarding' }" @click="activeTab = 'onboarding'">
        <el-icon><Shop /></el-icon><span>入驻</span>
      </button>
      <button :class="{ active: activeTab === 'vouchers' }" :disabled="!isApproved" @click="activeTab = 'vouchers'">
        <el-icon><Document /></el-icon><span>凭证</span>
      </button>
      <button :class="{ active: activeTab === 'mine' }" @click="activeTab = 'mine'">
        <el-icon><User /></el-icon><span>我的</span>
      </button>
    </nav>

    <el-drawer v-model="voucherDetailVisible" title="打款凭证详情" direction="btt" size="88%">
      <article v-if="selectedVoucher" class="voucher-detail">
        <div class="detail-title">
          <span>{{ selectedVoucher.voucherNo }}</span>
          <h3>{{ money(selectedVoucher.amount) }}</h3>
          <el-tag :type="selectedVoucher.status === 'PAID' ? 'success' : selectedVoucher.status === 'VOIDED' ? 'info' : 'warning'">
            {{ statusLabel(selectedVoucher.status) }}
          </el-tag>
        </div>
        <dl class="merchant-info">
          <dt>商家</dt><dd>{{ selectedVoucher.storeName || '-' }}</dd>
          <dt>打款时间</dt><dd>{{ selectedVoucher.paidAt || '-' }}</dd>
          <dt>收款主体</dt><dd>{{ selectedVoucher.payeeName }}</dd>
          <dt>收款账号</dt><dd>{{ selectedVoucher.payeeAccountMasked }}</dd>
          <dt>付款方</dt><dd>{{ selectedVoucher.payerName || '-' }}</dd>
          <dt>业务编号</dt><dd>{{ selectedVoucher.relatedBusinessNo || '-' }}</dd>
          <dt>备注</dt><dd>{{ selectedVoucher.remark || '-' }}</dd>
          <dt v-if="selectedVoucher.voidReason">作废原因</dt><dd v-if="selectedVoucher.voidReason">{{ selectedVoucher.voidReason }}</dd>
        </dl>
        <el-button
          v-if="selectedVoucher.voucherFile"
          type="primary"
          plain
          @click="previewFile(selectedVoucher.voucherFile.filePath)"
        >
          预览凭证
        </el-button>
      </article>
    </el-drawer>
  </section>
</template>

<style scoped>
.merchant-h5 {
  min-height: calc(100vh - 112px);
  padding: 20px 20px 96px;
  background: linear-gradient(180deg, #eef8f7 0%, #f7fbff 44%, #ffffff 100%);
}

.merchant-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 22px;
  border-radius: 24px;
  color: #12323a;
  background: linear-gradient(135deg, #dff6f1 0%, #eef7ff 100%);
  border: 1px solid #d6ebe8;
}

.merchant-hero h2 {
  margin: 10px 0 8px;
  font-size: 28px;
}

.merchant-hero p,
.merchant-status-card p {
  margin: 0;
  color: #5f7480;
  line-height: 1.6;
}

.soft-chip {
  display: inline-flex;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(45, 153, 132, 0.12);
  color: #16816e;
  font-weight: 700;
}

.merchant-status-card,
.h5-panel {
  margin-top: 16px;
  padding: 18px;
  border: 1px solid #e1ecef;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 12px 32px rgba(28, 76, 90, 0.08);
}

.merchant-status-card span {
  color: #71838f;
}

.merchant-status-card strong {
  display: block;
  margin: 8px 0;
  font-size: 24px;
  color: #15323c;
}

.panel-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.panel-heading h3 {
  margin: 0;
  font-size: 22px;
}

.merchant-form :deep(.el-input__wrapper),
.merchant-form :deep(.el-textarea__inner) {
  min-height: 48px;
  border-radius: 14px;
}

.file-grid,
.merchant-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.file-grid article,
.merchant-metrics article {
  padding: 14px;
  border-radius: 16px;
  background: #f5faf9;
  border: 1px solid #dfefec;
}

.file-grid span,
.merchant-metrics span {
  display: block;
  margin-bottom: 8px;
  color: #6f828c;
}

.file-grid strong,
.merchant-metrics strong {
  color: #12323a;
}

.status-tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 8px;
  margin-bottom: 12px;
}

.status-tabs button,
.merchant-tabbar button {
  border: 0;
  background: transparent;
  cursor: pointer;
}

.status-tabs button {
  flex: 0 0 auto;
  padding: 8px 14px;
  border-radius: 999px;
  color: #607885;
  background: #f1f6f8;
}

.status-tabs button.active {
  color: #0f7666;
  background: #dff6f1;
  font-weight: 700;
}

.voucher-list {
  display: grid;
  gap: 12px;
}

.voucher-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border-radius: 18px;
  background: #ffffff;
  border: 1px solid #e3edf0;
}

.voucher-card span,
.voucher-card p {
  color: #6d7e88;
}

.voucher-card strong {
  display: block;
  margin: 8px 0;
  font-size: 24px;
  color: #12323a;
}

.merchant-info {
  display: grid;
  grid-template-columns: 110px 1fr;
  gap: 12px 16px;
  margin: 0;
}

.merchant-info dt {
  color: #778995;
}

.merchant-info dd {
  margin: 0;
  color: #17212b;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.merchant-tabbar {
  position: fixed;
  left: 50%;
  bottom: 16px;
  z-index: 20;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  width: min(420px, calc(100vw - 32px));
  transform: translateX(-50%);
  padding: 8px;
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid #dbe8eb;
  box-shadow: 0 18px 40px rgba(28, 76, 90, 0.18);
}

.merchant-tabbar button {
  display: grid;
  gap: 4px;
  place-items: center;
  padding: 10px;
  border-radius: 16px;
  color: #7a8a93;
}

.merchant-tabbar button.active {
  color: #0f7666;
  background: #e3f7f2;
  font-weight: 700;
}

.merchant-tabbar button:disabled {
  opacity: 0.42;
  cursor: not-allowed;
}

.detail-title {
  margin-bottom: 18px;
}

.detail-title span {
  color: #9a3b32;
}

.detail-title h3 {
  margin: 8px 0;
  font-size: 30px;
}

@media (max-width: 720px) {
  .merchant-h5 {
    min-height: 100vh;
    padding: 14px 14px 92px;
  }

  .merchant-hero {
    padding: 18px;
    border-radius: 20px;
  }

  .merchant-hero h2 {
    font-size: 24px;
  }

  .file-grid,
  .merchant-metrics {
    grid-template-columns: 1fr;
  }
}
</style>
