<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Close, Refresh, VideoPlay } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import H5AppFrame from '../../components/h5/H5AppFrame.vue'
import H5DetailSheet from '../../components/h5/H5DetailSheet.vue'
import H5FileUploadBox from '../../components/h5/H5FileUploadBox.vue'
import H5OrderCard from '../../components/h5/H5OrderCard.vue'
import H5OverviewCard from '../../components/h5/H5OverviewCard.vue'
import H5StatusTabs from '../../components/h5/H5StatusTabs.vue'
import {
  rejectInspectionTask,
  startInspectionTask,
  submitApplicationSupplement,
  submitInspectionTask,
  type ApplicationStatus,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'
import { useAuthStore } from '../../stores/auth'
import { h5ApplicationStatusLabel, h5Money, h5ProductImage } from '../../utils/h5Format'

const applications = useApplicationsStore()
const auth = useAuthStore()
const operating = ref(false)
const detailVisible = ref(false)
const activeTab = ref<'tasks' | 'intake' | 'mine'>('tasks')
const statusFilter = ref<ApplicationStatus | 'ALL'>('ALL')
const inspectionNote = ref('IMEI 与商家资料一致，外观轻微使用痕迹，功能检测通过。')
const rejectReason = ref('客户资料或设备照片不完整，请业务员补充后再验机。')
const supplementNote = ref('业务员已补充验机现场照片和设备检测说明。')

const salesTabs = [
  { key: 'tasks', label: '任务' },
  { key: 'intake', label: '录单台' },
  { key: 'mine', label: '我的' },
]
const statusTabs: Array<{ key: ApplicationStatus | 'ALL'; label: string }> = [
  { key: 'ALL', label: '全部' },
  { key: 'ASSIGNED', label: '待验机' },
  { key: 'INSPECTION_IN_PROGRESS', label: '验机中' },
  { key: 'NEEDS_SUPPLEMENT', label: '需补资料' },
  { key: 'PENDING_REVIEW', label: '待审核' },
  { key: 'REJECTED', label: '已驳回' },
  { key: 'PAID', label: '已打款' },
]

const intakeForm = reactive({
  storeCode: '',
  salesAgentName: auth.user?.display_name ?? auth.user?.username ?? '',
  productModel: 'iPhone 16 Pro Max',
  color: '',
  capacity: '',
  periodDays: '',
  salePrice: '',
  loanAmount: '',
  customerName: '',
  customerPhone: '',
  idType: '身份证',
  idNumber: '',
  customerAddress: '',
  emergencyName: '',
  emergencyRelation: '',
  emergencyPhone: '',
})

const selected = computed(() => applications.selected)
const latestTask = computed(() => selected.value?.inspectionTasks?.[0] ?? null)
const pageTitle = computed(() =>
  activeTab.value === 'intake' ? '录单台' : activeTab.value === 'mine' ? '我的' : '我的订单',
)
const activeApplications = computed(() =>
  applications.items.filter((item) =>
    ['ASSIGNED', 'INSPECTION_IN_PROGRESS', 'NEEDS_SUPPLEMENT', 'PENDING_REVIEW'].includes(
      item.status,
    ),
  ),
)
const filteredApplications = computed(() =>
  statusFilter.value === 'ALL'
    ? applications.items
    : applications.items.filter((item) => item.status === statusFilter.value),
)
const overviewStats = computed(() => [
  { label: '订单总数', value: applications.items.length },
  { label: '进行中订单', value: activeApplications.value.length },
])
const mineStats = computed(() => [
  { label: '待验机', value: countStatus('ASSIGNED') },
  { label: '验机中', value: countStatus('INSPECTION_IN_PROGRESS') },
  { label: '需补资料', value: countStatus('NEEDS_SUPPLEMENT') },
])
const canStart = computed(
  () => selected.value?.status === 'ASSIGNED' && latestTask.value?.status === 'ASSIGNED',
)
const canSubmitInspection = computed(
  () =>
    selected.value?.status === 'INSPECTION_IN_PROGRESS' &&
    latestTask.value?.status === 'IN_PROGRESS',
)
const canRejectInspection = computed(
  () =>
    selected.value?.status === 'INSPECTION_IN_PROGRESS' &&
    latestTask.value?.status === 'IN_PROGRESS',
)
const canSubmitSupplement = computed(
  () =>
    selected.value?.status === 'NEEDS_SUPPLEMENT' &&
    selected.value.currentOwnerRole === 'SALES' &&
    String(selected.value.currentOwnerUserId ?? '') === String(auth.user?.id ?? ''),
)

async function refresh(selectedId = applications.selectedId) {
  await applications.fetch()
  if (selectedId) {
    await applications.select(selectedId)
  }
}

async function openApplication(applicationId: string) {
  detailVisible.value = true
  await applications.select(applicationId)
}

function changeSalesTab(key: string) {
  if (key === 'tasks' || key === 'intake' || key === 'mine') {
    activeTab.value = key
  }
}

function changeStatusFilter(key: string) {
  if (key === 'ALL' || statusTabs.some((tab) => tab.key === key)) {
    statusFilter.value = key as ApplicationStatus | 'ALL'
  }
}

function countStatus(status: ApplicationStatus) {
  return applications.items.filter((item) => item.status === status).length
}

function saveDraft() {
  ElMessage.success('草稿已保存。')
}

function submitIntake() {
  ElMessage.info('当前版本由后台创建申请后指派业务员验机。')
}

function handleUploadPlaceholder() {
  ElMessage.info('当前版本保留上传入口，正式录单接口接入后可上传。')
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

function startTask() {
  if (latestTask.value) {
    runOperation(() => startInspectionTask(latestTask.value!.id), '已开始验机。')
  }
}

function submitTask() {
  if (latestTask.value) {
    runOperation(
      () =>
        submitInspectionTask(latestTask.value!.id, {
          inspectionResult: 'PASS',
          inspectionNote: inspectionNote.value,
        }),
      '验机结果已提交，等待审核。',
    )
  }
}

function rejectTask() {
  if (latestTask.value) {
    runOperation(
      () => rejectInspectionTask(latestTask.value!.id, rejectReason.value),
      '验机任务已退回补资料。',
    )
  }
}

function submitSupplement() {
  if (selected.value) {
    runOperation(
      () =>
        submitApplicationSupplement(selected.value!.id, {
          note: supplementNote.value,
        }),
      '业务员补充资料已提交。',
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
  applications.fetch()
})
</script>

<template>
  <H5AppFrame
    :title="pageTitle"
    :tabs="salesTabs"
    :active-tab="activeTab"
    @tab-change="changeSalesTab"
  >
    <template v-if="activeTab === 'tasks'">
      <H5OverviewCard eyebrow="ORDER CENTER" title="订单中心" :stats="overviewStats">
        <template #action>
          <el-button class="refresh-button" :icon="Refresh" circle plain @click="refresh()" />
        </template>
      </H5OverviewCard>

      <el-alert v-if="applications.error" type="error" :title="applications.error" show-icon />

      <H5StatusTabs :tabs="statusTabs" :active="statusFilter" @change="changeStatusFilter" />

      <div class="order-list" :class="{ loading: applications.loading }">
        <el-skeleton v-if="applications.loading" :rows="8" animated />
        <el-empty v-else-if="filteredApplications.length === 0" description="暂无匹配订单" />
        <H5OrderCard
          v-for="item in filteredApplications"
          v-else
          :key="item.id"
          class="clickable-order"
          :code="item.applicationNo"
          :title="`${item.brand} ${item.model}`"
          :subtitle="`商家：${item.storeName ?? '未记录'} / ${item.capacity ?? '-'} / ${
            item.color ?? '-'
          }`"
          :amount="`贷款 ${h5Money(item.loanAmount)}`"
          :status="h5ApplicationStatusLabel(item.status)"
          :image="h5ProductImage(item.model)"
          role="button"
          tabindex="0"
          @click="openApplication(item.id)"
          @keydown.enter.prevent="openApplication(item.id)"
        />
      </div>
    </template>

    <template v-else-if="activeTab === 'intake'">
      <section class="intake-stack">
        <div class="form-section">
          <h2>基础业务信息</h2>
          <div class="field-grid">
            <label>
              <span>门店编码</span>
              <input v-model="intakeForm.storeCode" placeholder="请输入门店编码" />
            </label>
            <label>
              <span>业务员</span>
              <input v-model="intakeForm.salesAgentName" placeholder="请输入业务员姓名" />
            </label>
            <label>
              <span>设备型号</span>
              <input v-model="intakeForm.productModel" placeholder="如 iPhone 16 Pro Max" />
            </label>
            <label>
              <span>颜色</span>
              <input v-model="intakeForm.color" placeholder="请输入颜色" />
            </label>
            <label>
              <span>容量</span>
              <input v-model="intakeForm.capacity" placeholder="如 256GB" />
            </label>
          </div>
        </div>

        <div class="form-section">
          <h2>账单配置</h2>
          <div class="field-grid">
            <label>
              <span>周期天数</span>
              <input v-model="intakeForm.periodDays" inputmode="numeric" placeholder="如 30" />
            </label>
            <label>
              <span>销售金额</span>
              <input v-model="intakeForm.salePrice" inputmode="decimal" placeholder="请输入金额" />
            </label>
            <label>
              <span>贷款金额</span>
              <input v-model="intakeForm.loanAmount" inputmode="decimal" placeholder="请输入金额" />
            </label>
          </div>
        </div>

        <div class="form-section">
          <h2>客户基础信息</h2>
          <div class="field-grid">
            <label>
              <span>客户姓名</span>
              <input v-model="intakeForm.customerName" placeholder="请输入姓名" />
            </label>
            <label>
              <span>联系电话</span>
              <input v-model="intakeForm.customerPhone" inputmode="tel" placeholder="请输入手机号" />
            </label>
            <label>
              <span>证件类型</span>
              <input v-model="intakeForm.idType" placeholder="请输入证件类型" />
            </label>
            <label>
              <span>证件号码</span>
              <input v-model="intakeForm.idNumber" placeholder="请输入证件号码" />
            </label>
            <label class="wide-field">
              <span>客户地址</span>
              <textarea v-model="intakeForm.customerAddress" rows="3" placeholder="请输入现住址" />
            </label>
          </div>
        </div>

        <div class="form-section">
          <h2>紧急联系人</h2>
          <div class="field-grid">
            <label>
              <span>联系人姓名</span>
              <input v-model="intakeForm.emergencyName" placeholder="请输入姓名" />
            </label>
            <label>
              <span>关系</span>
              <input v-model="intakeForm.emergencyRelation" placeholder="如 父母/配偶/朋友" />
            </label>
            <label>
              <span>联系电话</span>
              <input v-model="intakeForm.emergencyPhone" inputmode="tel" placeholder="请输入手机号" />
            </label>
          </div>
        </div>

        <div class="form-section upload-section">
          <h2>资料上传区</h2>
          <H5FileUploadBox
            label="身份证与客户资料"
            description="保留资料入口，后续接入正式录单接口。"
            @upload="handleUploadPlaceholder"
          />
          <H5FileUploadBox
            label="设备照片"
            description="上传设备正反面、IMEI 与验机现场资料。"
            @upload="handleUploadPlaceholder"
          />
        </div>

        <div class="intake-actions">
          <el-button plain @click="saveDraft">保存草稿</el-button>
          <el-button type="primary" @click="submitIntake">提交申请</el-button>
        </div>
      </section>
    </template>

    <template v-else>
      <section class="mine-stack">
        <H5OverviewCard
          eyebrow="SALES ACCOUNT"
          :title="auth.user?.display_name ?? auth.user?.username ?? '业务员'"
          :stats="mineStats"
        />

        <article class="profile-card">
          <div>
            <span>当前账号</span>
            <strong>{{ auth.user?.username ?? '未登录' }}</strong>
          </div>
          <div>
            <span>岗位角色</span>
            <strong>业务员</strong>
          </div>
          <p>处理平台指派的验机与补资料任务。</p>
        </article>
      </section>
    </template>

    <H5DetailSheet
      :visible="detailVisible"
      title="订单详情"
      @close="detailVisible = false"
    >
      <ApplicationDetail
        class="sales-detail"
        :application="applications.selected"
        :loading="applications.detailLoading"
        :logs="applications.logs"
        :logs-loading="applications.logsLoading"
        @load-logs="applications.loadLogs()"
      />

      <section class="sheet-operation-area">
        <h2>业务操作</h2>
        <div class="operation-form">
          <el-input v-model="inspectionNote" type="textarea" :rows="2" placeholder="验机备注" />
          <el-input v-model="rejectReason" type="textarea" :rows="2" placeholder="退回原因" />
          <el-input v-model="supplementNote" type="textarea" :rows="2" placeholder="补资料说明" />
        </div>
      </section>

      <template #footer>
        <div class="sheet-actions">
          <el-button :icon="VideoPlay" :disabled="!canStart" :loading="operating" @click="startTask">
            开始验机
          </el-button>
          <el-button
            type="primary"
            :icon="Check"
            :disabled="!canSubmitInspection"
            :loading="operating"
            @click="submitTask"
          >
            提交验机
          </el-button>
          <el-button
            type="warning"
            :icon="Close"
            :disabled="!canRejectInspection"
            :loading="operating"
            @click="rejectTask"
          >
            退回补资料
          </el-button>
          <el-button :disabled="!canSubmitSupplement" :loading="operating" @click="submitSupplement">
            提交补资料
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

.order-list,
.intake-stack,
.mine-stack {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.order-list.loading {
  padding: 4px 0;
}

.clickable-order {
  cursor: pointer;
  transition:
    transform 0.16s ease,
    box-shadow 0.16s ease;
}

.clickable-order:focus-visible,
.clickable-order:hover {
  transform: translateY(-1px);
  outline: 2px solid rgba(93, 120, 255, 0.34);
  outline-offset: 2px;
  box-shadow: 0 18px 36px rgba(61, 86, 150, 0.16);
}

.form-section,
.profile-card,
.sheet-operation-area {
  display: grid;
  gap: 14px;
  min-width: 0;
  padding: 18px;
  border: 1px solid var(--h5-border);
  border-radius: var(--h5-radius);
  background: var(--h5-card);
  box-shadow: var(--h5-shadow);
}

.form-section h2,
.sheet-operation-area h2 {
  margin: 0;
  color: var(--h5-ink);
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.35;
}

.form-section h2::after,
.sheet-operation-area h2::after {
  display: block;
  width: 32px;
  height: 3px;
  margin-top: 8px;
  border-radius: 999px;
  background: var(--h5-orange);
  content: "";
}

.field-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.field-grid label {
  display: grid;
  min-width: 0;
  gap: 7px;
}

.field-grid span {
  color: var(--h5-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.35;
}

.field-grid input,
.field-grid textarea {
  width: 100%;
  min-width: 0;
  border: 1px solid var(--h5-border);
  border-radius: 14px;
  background: var(--h5-soft);
  color: var(--h5-ink);
  font: inherit;
  font-size: 14px;
  line-height: 1.4;
  outline: none;
  transition:
    border-color 0.16s ease,
    box-shadow 0.16s ease,
    background 0.16s ease;
}

.field-grid input {
  height: 44px;
  padding: 0 13px;
}

.field-grid textarea {
  resize: vertical;
  padding: 12px 13px;
}

.field-grid input:focus,
.field-grid textarea:focus {
  border-color: var(--h5-blue);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(93, 120, 255, 0.12);
}

.wide-field {
  grid-column: 1 / -1;
}

.upload-section {
  box-shadow: none;
}

.intake-actions {
  position: sticky;
  bottom: calc(var(--h5-bottom-nav-height, 64px) + env(safe-area-inset-bottom, 0px) + 10px);
  z-index: 8;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--h5-border);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 12px 28px rgba(61, 86, 150, 0.13);
  backdrop-filter: blur(14px);
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

.sales-detail {
  border-color: var(--h5-border);
  border-radius: 18px;
  box-shadow: none;
}

.sheet-operation-area {
  margin-top: 14px;
  box-shadow: none;
}

.operation-form {
  display: grid;
  gap: 10px;
}

.sheet-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  width: 100%;
}

.sheet-actions .el-button {
  margin-left: 0;
}

:deep(.sales-detail .detail-title p),
:deep(.sales-detail .log-title p) {
  color: var(--h5-blue);
}

:deep(.sales-detail .detail-title h3),
:deep(.sales-detail .log-title h4),
:deep(.sales-detail .detail-section summary) {
  color: var(--h5-ink);
}

:deep(.sales-detail .detail-metrics div) {
  border-top-color: var(--h5-blue);
  background: var(--h5-soft);
}

:deep(.sales-detail .detail-metrics strong) {
  color: var(--h5-blue);
}

:deep(.sales-detail .status-steps span.done) {
  border-color: rgba(93, 120, 255, 0.28);
  background: var(--h5-soft);
  color: var(--h5-blue);
}

@media (max-width: 520px) {
  .field-grid {
    grid-template-columns: 1fr;
  }

  .sheet-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .sheet-actions .el-button {
    width: 100%;
  }
}
</style>
