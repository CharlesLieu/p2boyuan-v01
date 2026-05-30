import { apiClient, type ApiEnvelope } from '../client'

export type ApplicationStatus =
  | 'DRAFT'
  | 'PENDING_ASSIGNMENT'
  | 'ASSIGNED'
  | 'INSPECTION_IN_PROGRESS'
  | 'PENDING_REVIEW'
  | 'NEEDS_SUPPLEMENT'
  | 'REJECTED'
  | 'PENDING_PAYOUT'
  | 'PAID'
  | 'COMPLETED'

export type OwnerRole = 'STORE' | 'SALES' | 'AUDITOR' | 'CASHIER' | 'SUPER_ADMIN' | null

export interface InspectionTask {
  id: string
  salesAgentId: string | null
  salesAgentName: string | null
  status: string
  inspectionNote: string | null
  startedAt: string | null
  submittedAt: string | null
}

export interface AttachmentInfo {
  id: string
  applicationId?: string | null
  module?: string | null
  fileName?: string | null
  filePath?: string | null
  mimeType?: string | null
  fileSize?: number | null
  remark?: string | null
  createdAt?: string | null
}

export interface ReviewRecord {
  id: string
  applicationId?: string | null
  reviewerUserId?: string | null
  reviewerName?: string | null
  action: string | null
  fromStatus?: ApplicationStatus | null
  toStatus?: ApplicationStatus | null
  note?: string | null
  createdAt?: string | null
}

export interface PayoutRecord {
  id: string
  amount: number
  status: string
  cashierUserId?: string | null
  voucherAttachmentId?: string | null
  voucherAttachment?: AttachmentInfo | null
  voucher?: AttachmentInfo | null
  paidAt: string | null
  remark: string | null
  createdAt?: string | null
}

export interface ApplicationItem {
  id: string
  applicationNo: string
  sourceType: string
  storeId: string | null
  storeName: string | null
  createdByUserId: string | null
  currentOwnerRole: OwnerRole
  currentOwnerUserId: string | null
  status: ApplicationStatus
  customerName: string
  customerPhone: string
  idType: string
  idNumber: string
  customerAddress: string
  brand: string
  model: string
  color: string | null
  capacity: string | null
  imei: string | null
  deviceCondition: string | null
  salePrice: number
  loanAmount: number
  periods: number
  remark: string | null
  createdAt: string | null
  updatedAt: string | null
  inspectionTasks?: InspectionTask[]
  reviewRecords?: ReviewRecord[]
  payoutRecords?: PayoutRecord[]
}

export interface ApplicationLog {
  id: string
  applicationId: string
  actorUserId: string | null
  actorName: string | null
  actorRole: OwnerRole
  fromStatus: ApplicationStatus | null
  toStatus: ApplicationStatus | null
  message: string
  action: string | null
  metadata: Record<string, unknown> | null
  createdAt: string | null
}

export async function listApplications(limit = 50) {
  const response = await apiClient.get<ApiEnvelope<{ items: ApplicationItem[] }>>('/applications', {
    params: { limit },
  })

  return response.data.data.items
}

export async function getApplication(applicationId: string) {
  const response = await apiClient.get<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}`,
  )

  return response.data.data.application
}

export async function getApplicationLogs(applicationId: string) {
  const response = await apiClient.get<ApiEnvelope<{ items: ApplicationLog[] }>>(
    `/applications/${applicationId}/logs`,
  )

  return response.data.data.items
}
