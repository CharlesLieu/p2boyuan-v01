<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import ApplicationDetail from '../../components/application/ApplicationDetail.vue'
import ApplicationList from '../../components/application/ApplicationList.vue'
import {
  createApplication,
  submitApplicationSupplement,
  type ApplicationCreatePayload,
} from '../../api/modules/applications'
import { useApplicationsStore } from '../../stores/applications'
import { useAuthStore } from '../../stores/auth'

const applications = useApplicationsStore()
const auth = useAuthStore()
const createVisible = ref(false)
const supplementVisible = ref(false)
const operating = ref(false)
const supplementNote = ref('补充客户资料、门店确认单和设备照片。')
const form = reactive<ApplicationCreatePayload>({
  customerName: '测试客户',
  customerPhone: '13800000001',
  idType: 'ID_CARD',
  idNumber: '440101199001010011',
  customerAddress: '广州市天河区测试路 88 号',
  brand: 'Apple',
  model: 'iPhone 16 Pro',
  color: '黑色',
  capacity: '256GB',
  imei: '359000000000001',
  deviceCondition: '外观轻微使用痕迹，屏幕功能正常。',
  salePrice: 8999,
  loanAmount: 3215,
  periods: 12,
  remark: '门店代客户提交远程彩排申请。',
})

const selected = computed(() => applications.selected)
const canSubmitSupplement = computed(
  () =>
    selected.value?.status === 'NEEDS_SUPPLEMENT' &&
    selected.value.currentOwnerRole === 'STORE' &&
    String(selected.value.currentOwnerUserId ?? '') === String(auth.user?.id ?? ''),
)
const pendingCount = computed(
  () =>
    applications.items.filter((item) =>
      ['PENDING_ASSIGNMENT', 'NEEDS_SUPPLEMENT', 'REJECTED'].includes(item.status),
    ).length,
)

async function refresh(selectedId = applications.selectedId) {
  await applications.fetch()
  if (selectedId) {
    await applications.select(selectedId)
  }
}

async function submitCreate() {
  operating.value = true

  try {
    const application = await createApplication({ ...form })
    ElMessage.success('申请已提交，等待审核员派单。')
    createVisible.value = false
    await refresh(application.id)
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
  }
}

async function submitSupplement() {
  if (!selected.value) {
    return
  }

  operating.value = true

  try {
    await submitApplicationSupplement(selected.value.id, {
      note: supplementNote.value,
      attachments: [
        {
          fileName: 'store-supplement-demo.pdf',
          filePath: '/demo/store-supplement-demo.pdf',
          mimeType: 'application/pdf',
          remark: '远程彩排补资料占位附件。',
        },
      ],
    })
    ElMessage.success('补充资料已提交。')
    supplementVisible.value = false
    await refresh(selected.value.id)
  } catch (error) {
    ElMessage.error(errorMessage(error))
  } finally {
    operating.value = false
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
  <section class="workspace-page">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">店家提交</el-tag>
        <h2>店家工作台</h2>
        <p>门店协助客户提交验机申请，查看自己的申请进度，并在被驳回补资料时补交材料。</p>
      </div>
      <div class="hero-actions">
        <el-button :icon="Refresh" plain @click="refresh()">刷新</el-button>
        <el-button type="danger" :icon="Plus" @click="createVisible = true">新建申请</el-button>
      </div>
    </div>

    <div class="summary-grid">
      <article><strong>门店可见</strong><span>{{ applications.items.length }} 单</span></article>
      <article><strong>待门店处理</strong><span>{{ pendingCount }} 单</span></article>
      <article><strong>当前选中</strong><span>{{ selected?.applicationNo ?? '未选择' }}</span></article>
      <article><strong>彩排账号</strong><span>{{ auth.user?.username }}</span></article>
    </div>

    <el-alert v-if="applications.error" type="error" :title="applications.error" show-icon />

    <div class="role-actions">
      <el-button
        type="warning"
        :disabled="!canSubmitSupplement"
        :loading="operating"
        @click="supplementVisible = true"
      >
        提交补资料
      </el-button>
      <span>仅当申请状态为“需要补资料”且轮到当前门店账号时可操作。</span>
    </div>

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

    <el-drawer v-model="createVisible" title="新建验机申请" size="520px">
      <el-form label-position="top" class="drawer-form">
        <el-form-item label="客户姓名"><el-input v-model="form.customerName" /></el-form-item>
        <el-form-item label="客户手机"><el-input v-model="form.customerPhone" /></el-form-item>
        <el-form-item label="证件号码"><el-input v-model="form.idNumber" /></el-form-item>
        <el-form-item label="客户地址"><el-input v-model="form.customerAddress" /></el-form-item>
        <el-form-item label="品牌"><el-input v-model="form.brand" /></el-form-item>
        <el-form-item label="型号"><el-input v-model="form.model" /></el-form-item>
        <el-form-item label="颜色/容量">
          <div class="inline-fields">
            <el-input v-model="form.color" />
            <el-input v-model="form.capacity" />
          </div>
        </el-form-item>
        <el-form-item label="IMEI"><el-input v-model="form.imei" /></el-form-item>
        <el-form-item label="成色说明">
          <el-input v-model="form.deviceCondition" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="销售价/贷款额/期数">
          <div class="inline-fields">
            <el-input-number v-model="form.salePrice" :min="0" />
            <el-input-number v-model="form.loanAmount" :min="0" />
            <el-input-number v-model="form.periods" :min="1" :max="60" />
          </div>
        </el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="danger" :loading="operating" @click="submitCreate">提交申请</el-button>
      </template>
    </el-drawer>

    <el-dialog v-model="supplementVisible" title="提交补充资料" width="520px">
      <el-input v-model="supplementNote" type="textarea" :rows="5" />
      <template #footer>
        <el-button @click="supplementVisible = false">取消</el-button>
        <el-button type="danger" :loading="operating" @click="submitSupplement">提交</el-button>
      </template>
    </el-dialog>
  </section>
</template>
