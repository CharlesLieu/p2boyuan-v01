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
  applicationId?: string | null
  amount: number
  status: string
  cashierUserId?: string | null
  voucherAttachmentId?: string | null
  voucherAttachment?: AttachmentInfo | null
  voucher?: AttachmentInfo | null
  paidAt: string | null
  remark: string | null
  createdAt?: string | null
  application?: Partial<ApplicationItem> | null
}

export interface ApplicationCreatePayload {
  customerName: string
  customerPhone: string
  idType: string
  idNumber: string
  customerAddress: string
  brand: string
  model: string
  color?: string | null
  capacity?: string | null
  imei?: string | null
  deviceCondition?: string | null
  salePrice: number
  loanAmount: number
  periods: number
  remark?: string | null
  storeId?: string | null
}

export interface AttachmentPayload {
  fileName: string
  filePath: string
  mimeType?: string | null
  fileSize?: number | null
  remark?: string | null
}

export interface UploadAttachmentPayload {
  applicationId: string
  module: 'APPLICATION' | 'INSPECTION' | 'SUPPLEMENT' | 'PAYOUT' | 'VOUCHER' | 'OTHER'
  file: File
  remark?: string | null
}

export interface DemoAccount {
  id: number
  username: string
  role: Exclude<OwnerRole, null>
  name: string
  status: string
  store: { id: string; storeCode: string; name: string; status: string } | null
  salesAgent: {
    id: string
    agentCode: string
    name: string
    region: string | null
    taskStatus: string | null
    status: string
  } | null
  lastLoginAt: string | null
}

export interface SalesAgentOption {
  id: string
  code: string
  name: string
  phone: string | null
  status: string
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

export async function listSalesAgents() {
  const response = await apiClient.get<ApiEnvelope<{ items: SalesAgentOption[] }>>('/sales-agents')

  return response.data.data.items
}

export async function createApplication(payload: ApplicationCreatePayload) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    '/applications',
    payload,
  )

  return response.data.data.application
}

export async function assignApplication(
  applicationId: string,
  payload: { salesAgentId: string; remark?: string | null },
) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}/assign`,
    payload,
  )

  return response.data.data.application
}

export async function approveApplication(applicationId: string, note?: string | null) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}/approve`,
    { note },
  )

  return response.data.data.application
}

export async function rejectApplication(applicationId: string, note: string) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}/reject`,
    { note },
  )

  return response.data.data.application
}

export async function requestApplicationSupplement(
  applicationId: string,
  payload: { ownerRole: 'SALES'; note: string },
) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}/request-supplement`,
    payload,
  )

  return response.data.data.application
}

export async function submitApplicationSupplement(
  applicationId: string,
  payload: { note: string; attachments?: AttachmentPayload[] },
) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/applications/${applicationId}/supplement`,
    payload,
  )

  return response.data.data.application
}

export async function startInspectionTask(inspectionTaskId: string) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/inspection-tasks/${inspectionTaskId}/start`,
  )

  return response.data.data.application
}

export async function submitInspectionTask(
  inspectionTaskId: string,
  payload: {
    inspectionResult: 'PASS' | 'FAIL'
    inspectionNote: string
    attachments?: AttachmentPayload[]
  },
) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/inspection-tasks/${inspectionTaskId}/submit`,
    payload,
  )

  return response.data.data.application
}

export async function rejectInspectionTask(inspectionTaskId: string, reason: string) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/inspection-tasks/${inspectionTaskId}/reject`,
    { reason },
  )

  return response.data.data.application
}

export async function uploadAttachment(payload: UploadAttachmentPayload) {
  const formData = new FormData()
  formData.append('applicationId', payload.applicationId)
  formData.append('module', payload.module)
  formData.append('file', payload.file)

  if (payload.remark) {
    formData.append('remark', payload.remark)
  }

  const response = await apiClient.post<ApiEnvelope<{ attachment: AttachmentInfo }>>(
    '/attachments',
    formData,
  )

  return response.data.data.attachment
}

export async function listPayouts(limit = 50) {
  const response = await apiClient.get<ApiEnvelope<{ items: PayoutRecord[] }>>('/payouts', {
    params: { limit },
  })

  return response.data.data.items
}

export async function confirmPayout(
  payoutId: string,
  payload: {
    amount: number
    paidAt?: string | null
    voucher: AttachmentPayload
    remark?: string | null
  },
) {
  const response = await apiClient.post<ApiEnvelope<{ payoutRecord: PayoutRecord }>>(
    `/payouts/${payoutId}/confirm`,
    payload,
  )

  return response.data.data.payoutRecord
}

export async function listDemoAccounts() {
  const response = await apiClient.get<ApiEnvelope<{ items: DemoAccount[] }>>('/admin/accounts')

  return response.data.data.items
}

export async function resetDemoData() {
  const response = await apiClient.post<ApiEnvelope<unknown>>('/admin/reset-demo-data', {
    confirm: true,
  })

  return response.data.data
}

export async function overrideApplicationStatus(
  applicationId: string,
  payload: {
    status: ApplicationStatus
    currentOwnerRole: OwnerRole
    currentOwnerUserId?: number | null
    remark?: string | null
  },
) {
  const response = await apiClient.post<ApiEnvelope<{ application: ApplicationItem }>>(
    `/admin/applications/${applicationId}/status`,
    payload,
  )

  return response.data.data.application
}
