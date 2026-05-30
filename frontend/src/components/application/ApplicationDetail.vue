<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from './StatusBadge.vue'
import type {
  ApplicationItem,
  ApplicationLog,
  AttachmentInfo,
} from '../../api/modules/applications'

const props = defineProps<{
  application: ApplicationItem | null
  logs: ApplicationLog[]
  loading?: boolean
  logsLoading?: boolean
}>()

const emit = defineEmits<{
  loadLogs: []
}>()

const latestInspection = computed(() => props.application?.inspectionTasks?.[0] ?? null)
const latestPayout = computed(() => props.application?.payoutRecords?.[0] ?? null)
const latestReview = computed(() => {
  const records = props.application?.reviewRecords ?? []

  return [...records].sort(compareCreatedAtDesc)[0] ?? null
})
const latestReviewLog = computed(() => {
  const reviewActions = ['APPROVE', 'REJECT', 'REQUEST_SUPPLEMENT', 'SUBMIT_SUPPLEMENT']

  return [...props.logs]
    .filter((log) => reviewActions.some((action) => String(log.action ?? '').includes(action)))
    .sort(compareCreatedAtDesc)[0] ?? null
})
const reviewDisplay = computed(() => {
  const record = latestReview.value

  if (record) {
    return {
      action: reviewActionText(record.action),
      result: statusTransition(record.fromStatus, record.toStatus),
      note: text(record.note, '本次审核未填写备注。'),
      actor: text(record.reviewerName, '审核员'),
      time: dateText(record.createdAt),
    }
  }

  const log = latestReviewLog.value

  if (log) {
    return {
      action: reviewActionText(log.action),
      result: statusTransition(log.fromStatus, log.toStatus),
      note: reviewNoteFromLog(log),
      actor: text(log.actorName, '系统'),
      time: dateText(log.createdAt),
    }
  }

  return null
})
const supplementOrRejectReason = computed(() => {
  const records = [...(props.application?.reviewRecords ?? [])].sort(compareCreatedAtDesc)
  const record = records.find((item) => {
    const action = String(item.action ?? '')
    return action.includes('REJECT') || action.includes('SUPPLEMENT')
  })

  if (record) {
    return text(record.note, '未填写具体原因。')
  }

  const log = [...props.logs].sort(compareCreatedAtDesc).find((item) => {
    const action = String(item.action ?? '')
    const status = String(item.toStatus ?? '')
    return action.includes('REJECT') || action.includes('SUPPLEMENT') || status === 'NEEDS_SUPPLEMENT'
  })

  if (log) {
    return reviewNoteFromLog(log)
  }

  return '暂无驳回或补资料原因记录。'
})
const payoutVoucher = computed(() => {
  const payout = latestPayout.value
  const attachment = payout?.voucherAttachment ?? payout?.voucher ?? null

  if (attachment) {
    return attachment
  }

  if (payout?.voucherAttachmentId) {
    return {
      id: payout.voucherAttachmentId,
      fileName: null,
      filePath: null,
    } satisfies AttachmentInfo
  }

  return null
})

function money(value: number | string | null | undefined) {
  return `￥${Number(value ?? 0).toLocaleString('zh-CN', { minimumFractionDigits: 0 })}`
}

function text(value: unknown, fallback = '待补充') {
  return value === null || value === undefined || value === '' ? fallback : String(value)
}

function dateText(value: string | null | undefined) {
  if (!value) {
    return '未记录'
  }

  return new Date(value).toLocaleString('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function compareCreatedAtDesc(
  left: { createdAt?: string | null },
  right: { createdAt?: string | null },
) {
  return new Date(right.createdAt ?? 0).getTime() - new Date(left.createdAt ?? 0).getTime()
}

function statusTransition(fromStatus: string | null | undefined, toStatus: string | null | undefined) {
  if (!fromStatus && !toStatus) {
    return '未记录状态变化'
  }

  if (!fromStatus) {
    return text(toStatus)
  }

  return `${fromStatus} → ${text(toStatus)}`
}

function reviewActionText(action: string | null | undefined) {
  const actionText: Record<string, string> = {
    APPROVE: '审核通过',
    REJECT: '审核驳回',
    REQUEST_SUPPLEMENT: '要求补充资料',
    SUBMIT_SUPPLEMENT: '提交补充资料',
  }

  return actionText[String(action ?? '')] ?? text(action, '暂无审核记录')
}

function reviewNoteFromLog(log: ApplicationLog) {
  const metadata = log.metadata ?? {}
  const note =
    metadata.note ??
    metadata.remark ??
    metadata.reason ??
    metadata.reviewNote ??
    metadata.supplementNote ??
    log.message

  return text(note, '未填写具体原因。')
}

function voucherTitle(attachment: AttachmentInfo) {
  return attachment.fileName || `凭证附件 ID：${attachment.id}`
}
</script>

<template>
  <section class="application-detail">
    <el-skeleton v-if="loading" :rows="12" animated />

    <el-empty v-else-if="!application" description="请选择一笔申请查看详情" />

    <template v-else>
      <div class="detail-title">
        <div>
          <p>{{ application.applicationNo }}</p>
          <h3>{{ application.customerName }}的{{ application.brand }} {{ application.model }}</h3>
        </div>
        <StatusBadge :status="application.status" />
      </div>

      <div class="detail-metrics">
        <div>
          <span>销售金额</span>
          <strong>{{ money(application.salePrice) }}</strong>
        </div>
        <div>
          <span>贷款金额</span>
          <strong>{{ money(application.loanAmount) }}</strong>
        </div>
        <div>
          <span>分期期数</span>
          <strong>{{ application.periods }} 期</strong>
        </div>
      </div>

      <div class="detail-sections">
        <section>
          <h4>客户信息</h4>
          <dl>
            <dt>姓名</dt>
            <dd>{{ application.customerName }}</dd>
            <dt>手机号</dt>
            <dd>{{ application.customerPhone }}</dd>
            <dt>证件</dt>
            <dd>{{ application.idType }} / {{ application.idNumber }}</dd>
            <dt>地址</dt>
            <dd>{{ text(application.customerAddress) }}</dd>
          </dl>
        </section>

        <section>
          <h4>设备信息</h4>
          <dl>
            <dt>品牌型号</dt>
            <dd>{{ application.brand }} {{ application.model }}</dd>
            <dt>颜色容量</dt>
            <dd>{{ text(application.color) }} / {{ text(application.capacity) }}</dd>
            <dt>IMEI</dt>
            <dd>{{ text(application.imei) }}</dd>
            <dt>成色说明</dt>
            <dd>{{ text(application.deviceCondition) }}</dd>
          </dl>
        </section>

        <section>
          <h4>门店与业务员</h4>
          <dl>
            <dt>门店</dt>
            <dd>{{ text(application.storeName) }}</dd>
            <dt>当前负责人</dt>
            <dd>{{ text(application.currentOwnerRole, '暂无负责人') }}</dd>
            <dt>业务员</dt>
            <dd>{{ text(latestInspection?.salesAgentName) }}</dd>
            <dt>验机状态</dt>
            <dd>{{ text(latestInspection?.status) }}</dd>
          </dl>
        </section>

        <section>
          <h4>验机/审核/打款</h4>
          <dl>
            <dt>验机备注</dt>
            <dd>{{ text(latestInspection?.inspectionNote) }}</dd>
            <dt>提交验机</dt>
            <dd>{{ dateText(latestInspection?.submittedAt) }}</dd>
            <dt>审核动作</dt>
            <dd>{{ text(reviewDisplay?.action, '暂无审核记录') }}</dd>
            <dt>审核结果</dt>
            <dd>{{ text(reviewDisplay?.result, '暂无审核结果') }}</dd>
            <dt>审核意见</dt>
            <dd>{{ text(reviewDisplay?.note, '暂无审核意见') }}</dd>
            <dt>审核人</dt>
            <dd>{{ text(reviewDisplay?.actor, '未记录') }} / {{ text(reviewDisplay?.time, '未记录') }}</dd>
            <dt>驳回/补资料</dt>
            <dd>{{ supplementOrRejectReason }}</dd>
            <dt>打款状态</dt>
            <dd>{{ text(latestPayout?.status) }}</dd>
            <dt>打款时间</dt>
            <dd>{{ dateText(latestPayout?.paidAt) }}</dd>
            <dt>打款备注</dt>
            <dd>{{ text(latestPayout?.remark) }}</dd>
            <dt>打款凭证</dt>
            <dd>
              <template v-if="payoutVoucher">
                <a
                  v-if="payoutVoucher.filePath"
                  :href="payoutVoucher.filePath"
                  target="_blank"
                  rel="noreferrer"
                >
                  {{ voucherTitle(payoutVoucher) }}
                </a>
                <span v-else>{{ voucherTitle(payoutVoucher) }}</span>
              </template>
              <span v-else>暂无打款凭证记录</span>
            </dd>
          </dl>
        </section>
      </div>

      <div class="log-panel">
        <div class="log-title">
          <div>
            <p>状态日志</p>
            <h4>审计轨迹</h4>
          </div>
          <el-button size="small" :loading="logsLoading" @click="emit('loadLogs')">加载日志</el-button>
        </div>

        <el-timeline v-if="logs.length > 0">
          <el-timeline-item
            v-for="log in logs"
            :key="log.id"
            :timestamp="dateText(log.createdAt)"
          >
            <strong>{{ log.message }}</strong>
            <span>{{ text(log.actorName, '系统') }} / {{ text(log.actorRole, 'SYSTEM') }}</span>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="点击加载查看状态日志" />
      </div>
    </template>
  </section>
</template>

<style scoped>
.application-detail {
  min-width: 0;
  padding: 22px;
  border: 1px solid #eceef4;
  border-radius: 8px;
  background: #fff;
}

.detail-title,
.log-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.detail-title p,
.detail-title h3,
.log-title p,
.log-title h4 {
  margin: 0;
}

.detail-title p,
.log-title p {
  color: #9a353b;
  font-size: 13px;
}

.detail-title h3 {
  margin-top: 4px;
  color: #171a22;
  font-size: 22px;
  line-height: 1.35;
}

.detail-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 18px;
}

.detail-metrics div {
  min-height: 86px;
  padding: 16px;
  border-top: 2px solid #d7232a;
  background: #fafbfc;
}

.detail-metrics span,
.detail-metrics strong {
  display: block;
}

.detail-metrics span {
  color: #7b818e;
  font-size: 13px;
}

.detail-metrics strong {
  margin-top: 8px;
  color: #b91720;
  font-size: 24px;
}

.detail-sections {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  margin-top: 20px;
}

.detail-sections section {
  min-width: 0;
}

.detail-sections h4 {
  margin: 0 0 12px;
  color: #171a22;
  font-size: 16px;
}

dl {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  gap: 10px 14px;
  margin: 0;
}

dt {
  color: #7b818e;
}

dd {
  min-width: 0;
  margin: 0;
  overflow-wrap: anywhere;
  color: #252934;
}

.log-panel {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #eceef4;
}

.el-timeline {
  margin-top: 18px;
  padding-left: 4px;
}

.el-timeline strong,
.el-timeline span {
  display: block;
}

.el-timeline span {
  margin-top: 5px;
  color: #747b88;
  font-size: 12px;
}

@media (max-width: 1180px) {
  .detail-metrics,
  .detail-sections {
    grid-template-columns: 1fr;
  }
}
</style>
