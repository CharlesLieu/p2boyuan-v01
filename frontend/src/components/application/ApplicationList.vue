<script setup lang="ts">
import StatusBadge from './StatusBadge.vue'
import type { ApplicationItem } from '../../api/modules/applications'

defineProps<{
  applications: ApplicationItem[]
  selectedId: string | null
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [applicationId: string]
}>()

function money(value: number | string | null | undefined) {
  return `￥${Number(value ?? 0).toLocaleString('zh-CN', { minimumFractionDigits: 0 })}`
}

function ownerLabel(application: ApplicationItem) {
  const roleMap: Record<string, string> = {
    STORE: '店家',
    SALES: '业务员',
    AUDITOR: '审核员',
    CASHIER: '出纳',
    SUPER_ADMIN: '超管',
  }

  return application.currentOwnerRole ? roleMap[application.currentOwnerRole] : '无'
}
</script>

<template>
  <section class="application-list">
    <div class="list-header">
      <div>
        <p>业务申请</p>
        <h3>流程列表</h3>
      </div>
      <el-tag type="danger" effect="plain">{{ applications.length }} 单</el-tag>
    </div>

    <el-skeleton v-if="loading" :rows="7" animated />

    <el-empty v-else-if="applications.length === 0" description="暂无可见申请" />

    <div v-else class="list-rows">
      <button
        v-for="application in applications"
        :key="application.id"
        class="application-row"
        :class="{ active: application.id === selectedId }"
        type="button"
        @click="emit('select', application.id)"
      >
        <span class="row-main">
          <strong>{{ application.applicationNo }}</strong>
          <small>{{ application.customerName }} / {{ application.customerPhone }}</small>
        </span>
        <span class="row-device">
          <strong>{{ application.brand }} {{ application.model }}</strong>
          <small>{{ application.capacity || '容量待补' }} / {{ application.color || '颜色待补' }}</small>
        </span>
        <span class="row-money">
          <strong>{{ money(application.loanAmount) }}</strong>
          <small>销售 {{ money(application.salePrice) }}</small>
        </span>
        <span class="row-status">
          <StatusBadge :status="application.status" />
          <small>{{ ownerLabel(application) }}</small>
        </span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.application-list {
  min-width: 0;
  padding: 18px;
  border: 1px solid #eceef4;
  border-radius: 8px;
  background: #fff;
}

.list-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.list-header p,
.list-header h3 {
  margin: 0;
}

.list-header p {
  color: #9a353b;
  font-size: 13px;
}

.list-header h3 {
  margin-top: 4px;
  color: #171a22;
  font-size: 20px;
}

.list-rows {
  display: grid;
  gap: 10px;
}

.application-row {
  display: grid;
  grid-template-columns: minmax(150px, 1.35fr) minmax(150px, 1.1fr) minmax(108px, 0.72fr) 116px;
  gap: 12px;
  align-items: center;
  width: 100%;
  min-height: 82px;
  padding: 14px;
  border: 1px solid #eceef4;
  border-radius: 8px;
  background: #fafbfc;
  color: #20242d;
  text-align: left;
  cursor: pointer;
}

.application-row:hover,
.application-row.active {
  border-color: #d7232a;
  background: #fff7f7;
}

.application-row span {
  min-width: 0;
}

.application-row strong,
.application-row small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.application-row strong {
  font-size: 14px;
}

.application-row small {
  margin-top: 6px;
  color: #747b88;
  font-size: 12px;
}

.row-status {
  display: grid;
  justify-items: end;
}

@media (max-width: 1180px) {
  .application-row {
    grid-template-columns: 1fr 1fr;
  }

  .row-status {
    justify-items: start;
  }
}

@media (max-width: 720px) {
  .application-row {
    grid-template-columns: 1fr;
  }
}
</style>
