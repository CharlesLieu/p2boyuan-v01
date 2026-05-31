<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, SetUp } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import ApplicationList from '../../components/application/ApplicationList.vue'
import {
  listDemoAccounts,
  overrideApplicationStatus,
  resetDemoData,
  type ApplicationStatus,
  type DemoAccount,
  type OwnerRole,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'

const applications = useApplicationsStore()
const accounts = ref<DemoAccount[]>([])
const accountsLoading = ref(false)
const operating = ref(false)
const statusForm = reactive<{
  status: ApplicationStatus
  currentOwnerRole: OwnerRole
  currentOwnerUserId: number | null
  remark: string
}>({
  status: 'PENDING_ASSIGNMENT',
  currentOwnerRole: 'AUDITOR',
  currentOwnerUserId: null,
  remark: '超级管理员手动调整业务测试数据状态。',
})

const statusOptions: ApplicationStatus[] = [
  'DRAFT',
  'PENDING_ASSIGNMENT',
  'ASSIGNED',
  'INSPECTION_IN_PROGRESS',
  'PENDING_REVIEW',
  'NEEDS_SUPPLEMENT',
  'REJECTED',
  'PENDING_PAYOUT',
  'PAID',
  'COMPLETED',
]
const ownerRoleOptions: OwnerRole[] = [null, 'STORE', 'SALES', 'AUDITOR', 'CASHIER']
const roleCounts = computed(() =>
  accounts.value.reduce<Record<string, number>>((counts, account) => {
    counts[account.role] = (counts[account.role] ?? 0) + 1
    return counts
  }, {}),
)

async function refresh(selectedId = applications.selectedId) {
  await Promise.all([applications.fetch(), fetchAccounts()])
  if (selectedId) {
    await applications.select(selectedId)
  }
}

async function fetchAccounts() {
  accountsLoading.value = true

  try {
    accounts.value = await listDemoAccounts()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    accountsLoading.value = false
  }
}

async function resetDemo() {
  try {
    await ElMessageBox.confirm('重置会恢复所有测试数据，当前业务进度会被清空。', '确认重置测试数据', {
      confirmButtonText: '确认重置',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  operating.value = true

  try {
    await resetDemoData()
    ElMessage.success('测试数据已重置。')
    await refresh()
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

async function submitStatusOverride() {
  if (!applications.selected) {
    return
  }

  operating.value = true

  try {
    await overrideApplicationStatus(applications.selected.id, {
      status: statusForm.status,
      currentOwnerRole: statusForm.currentOwnerRole,
      currentOwnerUserId: statusForm.currentOwnerUserId,
      remark: statusForm.remark,
    })
    ElMessage.success('申请状态已调整。')
    await refresh(applications.selected.id)
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

function useAccountOwner(account: DemoAccount) {
  if (account.role === 'SUPER_ADMIN') {
    statusForm.currentOwnerRole = null
    statusForm.currentOwnerUserId = null
    return
  }

  statusForm.currentOwnerRole = account.role
  statusForm.currentOwnerUserId = account.id
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
        <el-tag type="danger" effect="plain">管理控制台</el-tag>
        <h2>超级管理员</h2>
        <p>超管用于重置测试数据、查看所有账号，并在流程异常时手动修正申请状态。</p>
      </div>
      <div class="hero-actions">
        <el-button :icon="Refresh" plain @click="refresh()">刷新</el-button>
        <el-button type="danger" :icon="SetUp" :loading="operating" @click="resetDemo">一键重置</el-button>
      </div>
    </div>

    <div class="summary-grid">
      <article><strong>全部申请</strong><span>{{ applications.items.length }} 单</span></article>
      <article><strong>测试账号</strong><span>{{ accounts.length }} 个</span></article>
      <article><strong>业务员</strong><span>{{ roleCounts.SALES ?? 0 }} 个</span></article>
      <article><strong>门店</strong><span>{{ roleCounts.STORE ?? 0 }} 个</span></article>
    </div>

    <section class="admin-grid">
      <div class="table-panel">
        <div class="panel-heading">
          <h3>测试账号</h3>
          <el-tag type="danger" effect="plain">密码 123456</el-tag>
        </div>
        <el-table v-loading="accountsLoading" :data="accounts" height="300" row-key="id">
          <el-table-column prop="username" label="账号" min-width="110" />
          <el-table-column prop="name" label="姓名" min-width="120" />
          <el-table-column prop="role" label="角色" width="120" />
          <el-table-column label="归属" min-width="160">
            <template #default="{ row }">{{ row.store?.name ?? row.salesAgent?.name ?? '-' }}</template>
          </el-table-column>
          <el-table-column label="设为处理人" width="110">
            <template #default="{ row }">
              <el-button size="small" @click="useAccountOwner(row)">选择</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="operator-column">
        <h3>手动设置申请状态</h3>
        <el-select v-model="statusForm.status" placeholder="状态">
          <el-option v-for="status in statusOptions" :key="status" :label="status" :value="status" />
        </el-select>
        <el-select v-model="statusForm.currentOwnerRole" placeholder="当前处理角色" clearable>
          <el-option
            v-for="role in ownerRoleOptions"
            :key="role ?? 'NULL'"
            :label="role ?? '无处理人'"
            :value="role"
          />
        </el-select>
        <el-input-number v-model="statusForm.currentOwnerUserId" :min="1" placeholder="处理人用户 ID" />
        <el-input v-model="statusForm.remark" type="textarea" :rows="3" />
        <el-button type="danger" :disabled="!applications.selected" :loading="operating" @click="submitStatusOverride">
          保存状态
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
