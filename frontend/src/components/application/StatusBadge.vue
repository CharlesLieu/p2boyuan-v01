<script setup lang="ts">
import type { ApplicationStatus } from '../../api/modules/applications'

const props = defineProps<{
  status: ApplicationStatus | string
}>()

const statusMap: Record<
  ApplicationStatus,
  {
    label: string
    type: 'primary' | 'success' | 'warning' | 'info' | 'danger'
    effect: 'dark' | 'light' | 'plain'
  }
> = {
  DRAFT: { label: '草稿', type: 'info', effect: 'plain' },
  PENDING_ASSIGNMENT: { label: '待指派验机', type: 'warning', effect: 'light' },
  ASSIGNED: { label: '已指派', type: 'primary', effect: 'light' },
  INSPECTION_IN_PROGRESS: { label: '验机中', type: 'primary', effect: 'dark' },
  PENDING_REVIEW: { label: '待审核', type: 'warning', effect: 'dark' },
  NEEDS_SUPPLEMENT: { label: '需补资料', type: 'danger', effect: 'light' },
  REJECTED: { label: '已驳回', type: 'danger', effect: 'plain' },
  PENDING_PAYOUT: { label: '待打款', type: 'warning', effect: 'light' },
  PAID: { label: '已打款', type: 'success', effect: 'light' },
  COMPLETED: { label: '已完成', type: 'success', effect: 'dark' },
}

const option = statusMap[props.status as ApplicationStatus] ?? {
  label: props.status,
  type: 'info' as const,
  effect: 'plain' as const,
}
</script>

<template>
  <el-tag class="status-badge" :type="option.type" :effect="option.effect">
    {{ option.label }}
  </el-tag>
</template>

<style scoped>
.status-badge {
  min-width: 86px;
  justify-content: center;
}
</style>
