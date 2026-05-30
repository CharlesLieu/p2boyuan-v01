<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Money, Refresh } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import {
  confirmPayout,
  listPayouts,
  type PayoutRecord,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'

const applications = useApplicationsStore()
const payouts = ref<PayoutRecord[]>([])
const selectedPayoutId = ref<string | null>(null)
const loading = ref(false)
const operating = ref(false)
const form = reactive({
  amount: 0,
  paidAt: dateTimeValue(),
  remark: '远程彩排确认打款，凭证已上传。',
  voucherFileName: 'payout-voucher-demo.png',
  voucherFilePath: '/demo/payout-voucher-demo.png',
  voucherAttachmentId: '',
})

const selectedPayout = computed(
  () => payouts.value.find((payout) => payout.id === selectedPayoutId.value) ?? null,
)
const pendingPayouts = computed(() => payouts.value.filter((payout) => payout.status === 'PENDING'))
const paidAmount = computed(() =>
  payouts.value
    .filter((payout) => payout.status === 'PAID')
    .reduce((total, payout) => total + Number(payout.amount ?? 0), 0),
)

async function fetchPayouts() {
  loading.value = true

  try {
    payouts.value = await listPayouts()
    if (!selectedPayoutId.value || !payouts.value.some((item) => item.id === selectedPayoutId.value)) {
      selectedPayoutId.value = payouts.value[0]?.id ?? null
    }
    if (selectedPayout.value) {
      form.amount = Number(selectedPayout.value.amount ?? 0)
    }
    await loadSelectedApplication()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function loadSelectedApplication() {
  const applicationId = selectedPayout.value?.applicationId

  if (applicationId) {
    await applications.select(applicationId)
  }
}

async function confirmSelectedPayout() {
  if (!selectedPayout.value) {
    return
  }

  operating.value = true

  try {
    await confirmPayout(selectedPayout.value.id, {
      amount: form.amount || Number(selectedPayout.value.amount),
      paidAt: form.paidAt,
      remark: `${form.remark}${form.voucherAttachmentId ? ` 凭证编号：${form.voucherAttachmentId}` : ''}`,
      voucher: {
        fileName: form.voucherFileName,
        filePath: form.voucherFilePath,
        mimeType: 'image/png',
        remark: '出纳上传的打款凭证。',
      },
    })
    ElMessage.success('打款已确认。')
    await fetchPayouts()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

function selectPayout(payout: PayoutRecord) {
  selectedPayoutId.value = payout.id
  form.amount = Number(payout.amount ?? 0)
  loadSelectedApplication()
}

function money(value: number | string | null | undefined) {
  return `￥${Number(value ?? 0).toLocaleString('zh-CN', { minimumFractionDigits: 0 })}`
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
  <section class="workspace-page">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">打款确认</el-tag>
        <h2>出纳工作台</h2>
        <p>出纳查看待放款记录，确认打款金额和时间，并登记打款凭证供门店查看。</p>
      </div>
      <el-button :icon="Refresh" plain @click="fetchPayouts">刷新</el-button>
    </div>

    <div class="summary-grid">
      <article><strong>打款记录</strong><span>{{ payouts.length }} 笔</span></article>
      <article><strong>待打款</strong><span>{{ pendingPayouts.length }} 笔</span></article>
      <article><strong>已打款金额</strong><span>{{ money(paidAmount) }}</span></article>
      <article><strong>当前记录</strong><span>{{ selectedPayout?.status ?? '未选择' }}</span></article>
    </div>

    <section class="cashier-grid">
      <div class="table-panel">
        <div class="panel-heading">
          <h3>打款列表</h3>
          <el-tag type="danger" effect="plain">{{ payouts.length }} 笔</el-tag>
        </div>
        <el-table
          v-loading="loading"
          :data="payouts"
          row-key="id"
          highlight-current-row
          @row-click="selectPayout"
        >
          <el-table-column label="申请编号" min-width="150">
            <template #default="{ row }">{{ row.application?.applicationNo ?? row.applicationId }}</template>
          </el-table-column>
          <el-table-column label="客户" min-width="100">
            <template #default="{ row }">{{ row.application?.customerName ?? '-' }}</template>
          </el-table-column>
          <el-table-column label="金额" width="110">
            <template #default="{ row }">{{ money(row.amount) }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100" />
        </el-table>
      </div>

      <div class="operator-column">
        <h3>确认打款</h3>
        <el-input-number v-model="form.amount" :min="0" />
        <el-date-picker v-model="form.paidAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
        <el-input v-model="form.voucherAttachmentId" placeholder="凭证编号，可选" />
        <el-input v-model="form.voucherFileName" placeholder="凭证文件名" />
        <el-input v-model="form.voucherFilePath" placeholder="凭证展示路径" />
        <el-input v-model="form.remark" type="textarea" :rows="3" />
        <el-button
          type="danger"
          :icon="Money"
          :disabled="!selectedPayout || selectedPayout.status !== 'PENDING'"
          :loading="operating"
          @click="confirmSelectedPayout"
        >
          确认打款
        </el-button>
      </div>
    </section>

    <ApplicationDetail
      :application="applications.selected"
      :loading="applications.detailLoading"
      :logs="applications.logs"
      :logs-loading="applications.logsLoading"
      @load-logs="applications.loadLogs()"
    />
  </section>
</template>
