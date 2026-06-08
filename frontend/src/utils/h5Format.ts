import type { ApplicationStatus } from '../api/modules/applications'
import type { MerchantVoucherStatus } from '../api/modules/merchant'

export function h5Money(value: number | string | null | undefined, prefix = '¥') {
  return `${prefix}${Number(value ?? 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })}`
}

export function h5DateTime(value: string | null | undefined) {
  if (!value) return '暂无时间'
  return value.replace('T', ' ').slice(0, 16)
}

export function h5ApplicationStatusLabel(status: ApplicationStatus | string | null | undefined) {
  const labels: Record<string, string> = {
    DRAFT: '草稿',
    PENDING_ASSIGNMENT: '待指派',
    ASSIGNED: '待验机',
    INSPECTION_IN_PROGRESS: '验机中',
    PENDING_REVIEW: '待审核',
    NEEDS_SUPPLEMENT: '需补资料',
    REJECTED: '已驳回',
    PENDING_PAYOUT: '待打款',
    PAID: '已打款',
    COMPLETED: '已完成',
  }
  return labels[String(status ?? '')] ?? String(status ?? '未知')
}

export function h5VoucherStatusLabel(status: MerchantVoucherStatus | string | null | undefined) {
  const labels: Record<string, string> = {
    PENDING_CONFIRMATION: '待确认',
    PAID: '已打款',
    VOIDED: '已作废',
  }
  return labels[String(status ?? '')] ?? String(status ?? '未知')
}

export function h5AttachmentHref(path?: string | null) {
  if (!path) return null
  if (/^https?:\/\//.test(path) || path.startsWith('/')) return path
  return `/storage/${path}`
}

export function h5ProductImage(model?: string | null) {
  const normalized = String(model ?? '').toLowerCase()
  if (normalized.includes('17')) return '/demo/products/iphone-17-pro-max.svg'
  if (normalized.includes('16') && normalized.includes('pro max')) {
    return '/demo/products/iphone-16-pro-max.svg'
  }
  if (normalized.includes('16') && normalized.includes('pro')) return '/demo/products/iphone-16-pro.svg'
  if (normalized.includes('16')) return '/demo/products/iphone-16-pro.svg'
  if (normalized.includes('15') && normalized.includes('pro max')) {
    return '/demo/products/iphone-15-pro-max.svg'
  }
  if (normalized.includes('15') && normalized.includes('pro')) return '/demo/products/iphone-15-pro.svg'
  if (normalized.includes('15')) return '/demo/products/iphone-15.svg'
  if (normalized.includes('14') && normalized.includes('pro')) return '/demo/products/iphone-14-pro.svg'
  if (normalized.includes('14')) return '/demo/products/iphone-14.svg'
  if (normalized.includes('13')) return '/demo/products/iphone-13.svg'
  if (normalized.includes('12')) return '/demo/products/iphone-12.svg'
  return '/demo/products/iphone-default.svg'
}
