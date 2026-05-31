<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { DataBoard, Finished, Money, Tickets } from '@element-plus/icons-vue'
import ApplicationDetail from '../components/application/ApplicationDetail.vue'
import ApplicationList from '../components/application/ApplicationList.vue'
import { useApplicationsStore } from '../stores/applications'

const route = useRoute()
const applications = useApplicationsStore()

const title = computed(() => String(route.meta.title ?? '工作台'))
const badge = computed(() => String(route.meta.badge ?? '流程节点'))
const primaryAction = computed(() => String(route.meta.primaryAction ?? '继续处理'))
const pendingCount = computed(
  () =>
    applications.items.filter((item) =>
      [
        'PENDING_ASSIGNMENT',
        'ASSIGNED',
        'INSPECTION_IN_PROGRESS',
        'PENDING_REVIEW',
        'NEEDS_SUPPLEMENT',
        'PENDING_PAYOUT',
      ].includes(item.status),
    ).length,
)
const paidAmount = computed(() =>
  applications.items
    .filter((item) => ['PENDING_PAYOUT', 'PAID', 'COMPLETED'].includes(item.status))
    .reduce((total, item) => total + Number(item.loanAmount ?? 0), 0),
)

function money(value: number) {
  return `￥${value.toLocaleString('zh-CN', { minimumFractionDigits: 0 })}`
}

onMounted(() => {
  applications.fetch()
})
</script>

<template>
  <section class="workspace-page">
    <div class="workspace-hero">
      <div>
        <el-tag type="danger" effect="plain">{{ badge }}</el-tag>
        <h2>{{ title }}</h2>
        <p>围绕申请、验机、审核、补资料、打款凭证建立同一套业务数据，方便不同角色联动测试。</p>
      </div>
      <el-button type="danger" size="large" @click="applications.fetch()">{{ primaryAction }}</el-button>
    </div>

    <div class="summary-grid">
      <article>
        <el-icon><Tickets /></el-icon>
        <strong>待处理</strong>
        <span>{{ pendingCount }} 单</span>
      </article>
      <article>
        <el-icon><Finished /></el-icon>
        <strong>可见申请</strong>
        <span>{{ applications.items.length }} 单</span>
      </article>
      <article>
        <el-icon><Money /></el-icon>
        <strong>放款相关</strong>
        <span>{{ money(paidAmount) }}</span>
      </article>
      <article>
        <el-icon><DataBoard /></el-icon>
        <strong>业务数据</strong>
        <span>一致</span>
      </article>
    </div>

    <el-alert v-if="applications.error" type="error" :title="applications.error" show-icon />

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
