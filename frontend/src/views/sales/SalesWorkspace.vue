<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Close, Refresh, VideoPlay } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import ApplicationList from '../../components/application/ApplicationList.vue'
import {
  rejectInspectionTask,
  startInspectionTask,
  submitApplicationSupplement,
  submitInspectionTask,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'
import { useAuthStore } from '../../stores/auth'

const applications = useApplicationsStore()
const auth = useAuthStore()
const operating = ref(false)
const detailVisible = ref(false)
const isCompactScreen = ref(false)
const inspectionNote = ref('IMEI 与商家资料一致，外观轻微使用痕迹，功能检测通过。')
const rejectReason = ref('客户资料或设备照片不完整，请业务员补充后再验机。')
const supplementNote = ref('业务员已补充验机现场照片和设备检测说明。')

const selected = computed(() => applications.selected)
const latestTask = computed(() => selected.value?.inspectionTasks?.[0] ?? null)
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

async function handleApplicationSelect(applicationId: string) {
  await applications.select(applicationId)

  if (isCompactScreen.value) {
    detailVisible.value = true
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
          attachments: [
            {
              fileName: 'inspection-front.png',
              filePath: '/demo/inspection-front.png',
              mimeType: 'image/png',
              remark: '验机正面照片。',
            },
          ],
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
          attachments: [
            {
              fileName: 'sales-supplement-demo.png',
              filePath: '/demo/sales-supplement-demo.png',
              mimeType: 'image/png',
              remark: '业务员补充验机资料。',
            },
          ],
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

function syncCompactScreen() {
  isCompactScreen.value = window.matchMedia('(max-width: 720px)').matches
}

onMounted(() => {
  syncCompactScreen()
  window.addEventListener('resize', syncCompactScreen)
  applications.fetch()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncCompactScreen)
})
</script>

<template>
  <section class="workspace-page role-mobile">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">到店验机</el-tag>
        <h2>验机任务</h2>
        <p>处理被指派的到店验机任务，提交检测结论，并在需要时补充现场资料。</p>
      </div>
      <el-button :icon="Refresh" plain @click="refresh()">刷新</el-button>
    </div>

    <div class="summary-grid">
      <article><strong>相关申请</strong><span>{{ applications.items.length }} 单</span></article>
      <article><strong>待验机</strong><span>{{ applications.items.filter((item) => item.status === 'ASSIGNED').length }} 单</span></article>
      <article><strong>验机中</strong><span>{{ applications.items.filter((item) => item.status === 'INSPECTION_IN_PROGRESS').length }} 单</span></article>
      <article><strong>当前业务员</strong><span>{{ auth.user?.username }}</span></article>
    </div>

    <el-alert v-if="applications.error" type="error" :title="applications.error" show-icon />

    <div class="role-actions">
      <el-button :icon="VideoPlay" :disabled="!canStart" :loading="operating" @click="startTask">
        开始验机
      </el-button>
      <el-button type="danger" :icon="Check" :disabled="!canSubmitInspection" :loading="operating" @click="submitTask">
        提交验机
      </el-button>
      <el-button type="warning" :icon="Close" :disabled="!canRejectInspection" :loading="operating" @click="rejectTask">
        退回补资料
      </el-button>
      <span v-if="selected && !canRejectInspection">退回补资料需先开始验机。</span>
      <el-button :disabled="!canSubmitSupplement" :loading="operating" @click="submitSupplement">
        提交补资料
      </el-button>
    </div>

    <div class="operation-form">
      <el-input v-model="inspectionNote" type="textarea" :rows="2" placeholder="验机备注" />
      <el-input v-model="rejectReason" type="textarea" :rows="2" placeholder="退回原因" />
      <el-input v-model="supplementNote" type="textarea" :rows="2" placeholder="补资料说明" />
    </div>

    <div class="application-workbench">
      <ApplicationList
        :applications="applications.items"
        :loading="applications.loading"
        :selected-id="applications.selectedId"
        @select="handleApplicationSelect"
      />
      <ApplicationDetail
        class="desktop-application-detail"
        :application="applications.selected"
        :loading="applications.detailLoading"
        :logs="applications.logs"
        :logs-loading="applications.logsLoading"
        @load-logs="applications.loadLogs()"
      />
    </div>

    <el-drawer
      v-model="detailVisible"
      class="mobile-detail-drawer"
      direction="btt"
      size="92%"
      title="申请详情"
    >
      <ApplicationDetail
        :application="applications.selected"
        :loading="applications.detailLoading"
        :logs="applications.logs"
        :logs-loading="applications.logsLoading"
        @load-logs="applications.loadLogs()"
      />
      <template #footer>
        <div class="drawer-footer-actions sales-footer-actions">
          <el-button @click="detailVisible = false">关闭</el-button>
          <el-button :icon="VideoPlay" :disabled="!canStart" :loading="operating" @click="startTask">
            开始验机
          </el-button>
          <el-button
            type="danger"
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
        </div>
      </template>
    </el-drawer>
  </section>
</template>
