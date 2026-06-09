<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Close, Refresh, UserFilled, Warning } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import ApplicationList from '../../components/application/ApplicationList.vue'
import {
  approveApplication,
  assignApplication,
  listSalesAgents,
  rejectApplication,
  requestApplicationSupplement,
  type ApplicationStatus,
  type SalesAgentOption,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'

const applications = useApplicationsStore()
const operating = ref(false)
const salesAgentsLoading = ref(false)
const statusFilter = ref<ApplicationStatus | 'ALL'>('ALL')
const searchKeyword = ref('')
const salesAgentId = ref('')
const assignRemark = ref('请业务员到店完成设备验机并协助客户补齐申报资料。')
const reviewNote = ref('资料完整，验机结果符合放款要求。')
const rejectNote = ref('资料与验机结果不符合审核要求，本次申请驳回。')
const supplementOwnerRole = ref<'SALES'>('SALES')
const supplementNote = ref('请补充客户资料、设备照片或验机说明后重新提交。')

const selected = computed(() => applications.selected)
const salesAgentOptions = ref<SalesAgentOption[]>([])
const statusOptions: Array<{ label: string; value: ApplicationStatus | 'ALL' }> = [
  { label: '全部状态', value: 'ALL' },
  { label: '待派单', value: 'PENDING_ASSIGNMENT' },
  { label: '待审核', value: 'PENDING_REVIEW' },
  { label: '需补资料', value: 'NEEDS_SUPPLEMENT' },
  { label: '已驳回', value: 'REJECTED' },
  { label: '待打款', value: 'PENDING_PAYOUT' },
  { label: '已打款', value: 'PAID' },
]
const canAssign = computed(() => selected.value?.status === 'PENDING_ASSIGNMENT')
const canReview = computed(() => selected.value?.status === 'PENDING_REVIEW')

async function refresh(selectedId = applications.selectedId) {
  await applications.fetch({
    limit: 100,
    status: statusFilter.value === 'ALL' ? null : statusFilter.value,
    keyword: searchKeyword.value,
  })
  await discoverSalesAgents()
  if (selectedId) {
    await applications.select(selectedId)
  }
}

async function discoverSalesAgents() {
  salesAgentsLoading.value = true

  try {
    salesAgentOptions.value = await listSalesAgents()

    if (!salesAgentId.value && salesAgentOptions.value.length > 0) {
      salesAgentId.value = salesAgentOptions.value[0].id
    } else if (
      salesAgentId.value &&
      !salesAgentOptions.value.some((agent) => agent.id === salesAgentId.value)
    ) {
      salesAgentId.value = salesAgentOptions.value[0]?.id ?? ''
    }
  } finally {
    salesAgentsLoading.value = false
  }
}

async function runOperation(action: () => Promise<unknown>, message: string) {
  operating.value = true

  try {
    await action()
    ElMessage.success(message)
    await refresh(selected.value?.id)
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

function assignSales() {
  if (!selected.value) {
    return
  }

  if (!salesAgentId.value) {
    ElMessage.warning('请选择业务员。')
    return
  }

  runOperation(
    () =>
      assignApplication(selected.value!.id, {
        salesAgentId: salesAgentId.value,
        remark: assignRemark.value,
      }),
    '已指派业务员。',
  )
}

function approve() {
  if (selected.value) {
    runOperation(() => approveApplication(selected.value!.id, reviewNote.value), '审核通过，已进入待打款。')
  }
}

function reject() {
  if (selected.value) {
    runOperation(() => rejectApplication(selected.value!.id, rejectNote.value), '申请已驳回。')
  }
}

function requestSupplement() {
  if (selected.value) {
    runOperation(
      () =>
        requestApplicationSupplement(selected.value!.id, {
          ownerRole: supplementOwnerRole.value,
          note: supplementNote.value,
        }),
      '已发起补资料要求。',
    )
  }
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
  <section class="workspace-page role-desktop">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">派单审核</el-tag>
        <h2>审核员工作台</h2>
        <p>审核员负责指派业务员验机、复核验机结果，并决定通过、驳回或要求补充资料。</p>
      </div>
      <el-button :icon="Refresh" plain @click="refresh()">刷新</el-button>
    </div>

    <section class="filter-panel">
      <el-input
        v-model="searchKeyword"
        clearable
        placeholder="搜索申请编号、客户、机型、门店"
        @keyup.enter="refresh()"
        @clear="refresh()"
      />
      <el-select v-model="statusFilter" placeholder="状态" @change="refresh()">
        <el-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
      </el-select>
      <el-button type="primary" plain @click="refresh()">查询</el-button>
    </section>

    <div class="summary-grid">
      <article><strong>全部申请</strong><span>{{ applications.items.length }} 单</span></article>
      <article><strong>待派单</strong><span>{{ applications.items.filter((item) => item.status === 'PENDING_ASSIGNMENT').length }} 单</span></article>
      <article><strong>待审核</strong><span>{{ applications.items.filter((item) => item.status === 'PENDING_REVIEW').length }} 单</span></article>
      <article><strong>需补资料</strong><span>{{ applications.items.filter((item) => item.status === 'NEEDS_SUPPLEMENT').length }} 单</span></article>
    </div>

    <el-alert v-if="applications.error" type="error" :title="applications.error" show-icon />

    <section class="operator-panel">
      <div class="operator-column">
        <h3>指派业务员</h3>
        <el-select
          v-model="salesAgentId"
          :loading="salesAgentsLoading"
          filterable
          placeholder="选择业务员"
          no-data-text="暂无可用业务员，请先重置测试数据"
        >
          <el-option
            v-for="agent in salesAgentOptions"
            :key="agent.id"
            :label="`${agent.code} · ${agent.name}`"
            :value="agent.id"
          >
            <div class="agent-option">
              <strong>{{ agent.code }} · {{ agent.name }}</strong>
              <small>{{ agent.id }}</small>
            </div>
          </el-option>
        </el-select>
        <p class="field-hint">
          业务员来自后台主数据，派单时会使用真实 salesAgentId。
        </p>
        <el-input v-model="assignRemark" type="textarea" :rows="2" />
        <el-button type="danger" :icon="UserFilled" :disabled="!canAssign" :loading="operating" @click="assignSales">
          指派
        </el-button>
      </div>
      <div class="operator-column">
        <h3>审核处理</h3>
        <el-input v-model="reviewNote" type="textarea" :rows="2" />
        <div class="button-row">
          <el-button type="danger" :icon="Check" :disabled="!canReview" :loading="operating" @click="approve">
            通过
          </el-button>
          <el-button type="warning" :icon="Close" :disabled="!canReview" :loading="operating" @click="reject">
            驳回
          </el-button>
        </div>
      </div>
      <div class="operator-column">
        <h3>要求补资料</h3>
        <el-radio-group v-model="supplementOwnerRole">
          <el-radio-button label="SALES">业务员</el-radio-button>
        </el-radio-group>
        <el-input v-model="supplementNote" type="textarea" :rows="2" />
        <el-button :icon="Warning" :disabled="!canReview" :loading="operating" @click="requestSupplement">
          发起补资料
        </el-button>
      </div>
    </section>

    <div class="application-workbench">
      <ApplicationList
        :applications="applications.items"
        :loading="applications.loading"
        :selected-id="applications.selectedId"
        @select="applications.select"
      />
      <ApplicationDetail
        :application="applications.selected"
        :loading="applications.detailLoading"
        :logs="applications.logs"
        :logs-loading="applications.logsLoading"
        @load-logs="applications.loadLogs()"
      />
    </div>
  </section>
</template>

<style scoped>
.filter-panel {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) 180px auto;
  gap: 12px;
  align-items: center;
  padding: 16px;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  background: #fff;
  box-shadow: var(--shadow-soft);
}

@media (max-width: 900px) {
  .filter-panel {
    grid-template-columns: 1fr;
  }
}
</style>
