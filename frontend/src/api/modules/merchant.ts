import { apiClient, type ApiEnvelope } from '../client'

export type MerchantOnboardingStatus = 'DRAFT' | 'PENDING_REVIEW' | 'APPROVED' | 'REJECTED' | 'DISABLED'
export type MerchantVoucherStatus = 'PENDING_CONFIRMATION' | 'PAID' | 'VOIDED'

export interface MerchantFilePayload {
  fileName: string
  filePath: string
  mimeType: string
  fileSize?: number | null
}

export interface MerchantOnboardingPayload {
  applicantName: string
  applicantPhone: string
  applicantIdNumber: string
  merchantName: string
  merchantAddress: string
  contactName: string
  contactPhone: string
  paymentMethod: string
  paymentAccount: string
  paymentAccountName: string
  paymentBankOrChannel?: string | null
  idCardFrontFile: MerchantFilePayload
  idCardBackFile: MerchantFilePayload
  qualificationFile: MerchantFilePayload
}

export interface MerchantProfile {
  id: string
  storeCode: string
  name: string
  contactName: string | null
  contactPhone: string | null
  address: string | null
  status: string
  onboardingStatus: MerchantOnboardingStatus
  paymentMethod: string | null
  paymentAccountMasked: string | null
  paymentAccountName: string | null
  paymentBankOrChannel: string | null
  createdAt: string | null
  updatedAt: string | null
}

export interface MerchantOnboarding {
  id: string
  storeId: string | null
  storeName: string | null
  applicantName: string
  applicantPhone: string
  merchantName: string
  merchantAddress: string
  contactName: string
  contactPhone: string
  paymentMethod: string
  paymentAccountMasked: string | null
  paymentAccountName: string
  paymentBankOrChannel: string | null
  status: MerchantOnboardingStatus
  reviewerUserId: number | null
  reviewerName: string | null
  reviewedAt: string | null
  reviewNote: string | null
  rejectReason: string | null
  createdAt: string | null
  updatedAt: string | null
  idCardFrontFile?: MerchantFilePayload
  idCardBackFile?: MerchantFilePayload
  qualificationFile?: MerchantFilePayload
}

export interface MerchantVoucher {
  id: string
  voucherNo: string
  storeId: string
  storeName: string | null
  payoutRecordId: string | null
  relatedBusinessNo: string | null
  amount: number
  status: MerchantVoucherStatus
  paidAt: string | null
  payeeName: string
  payeeAccountMasked: string
  payerName: string | null
  remark: string | null
  voidReason: string | null
  voucherFile?: MerchantFilePayload
  createdAt: string | null
  updatedAt: string | null
}

export async function getMerchantMe() {
  const response = await apiClient.get<
    ApiEnvelope<{ profile: MerchantProfile; latestOnboarding: MerchantOnboarding | null }>
  >('/merchant/me')

  return response.data.data
}

export async function submitMerchantOnboarding(payload: MerchantOnboardingPayload) {
  const response = await apiClient.post<ApiEnvelope<{ onboarding: MerchantOnboarding }>>(
    '/merchant/onboarding',
    payload,
  )

  return response.data.data.onboarding
}

export async function listMerchantVouchers(limit = 50) {
  const response = await apiClient.get<ApiEnvelope<{ items: MerchantVoucher[] }>>(
    '/merchant/vouchers',
    { params: { limit } },
  )

  return response.data.data.items
}

export async function getMerchantVoucher(voucherId: string) {
  const response = await apiClient.get<ApiEnvelope<{ voucher: MerchantVoucher }>>(
    `/merchant/vouchers/${voucherId}`,
  )

  return response.data.data.voucher
}

export interface AdminMerchantVoucherPayload {
  storeId: string
  payoutRecordId?: string | null
  relatedBusinessNo?: string | null
  amount: number
  status: MerchantVoucherStatus
  paidAt?: string | null
  payeeName: string
  payeeAccountMasked: string
  payerName?: string | null
  voucherFile: MerchantFilePayload
  remark?: string | null
}

export async function listAdminMerchants(limit = 50) {
  const response = await apiClient.get<ApiEnvelope<{ items: MerchantOnboarding[] }>>(
    '/admin/merchants',
    { params: { limit } },
  )

  return response.data.data.items
}

export async function approveMerchant(onboardingId: string, note?: string | null) {
  const response = await apiClient.post<ApiEnvelope<{ onboarding: MerchantOnboarding }>>(
    `/admin/merchants/${onboardingId}/approve`,
    { note },
  )

  return response.data.data.onboarding
}

export async function rejectMerchant(onboardingId: string, rejectReason: string) {
  const response = await apiClient.post<ApiEnvelope<{ onboarding: MerchantOnboarding }>>(
    `/admin/merchants/${onboardingId}/reject`,
    { rejectReason },
  )

  return response.data.data.onboarding
}

export async function listAdminMerchantVouchers(limit = 50) {
  const response = await apiClient.get<ApiEnvelope<{ items: MerchantVoucher[] }>>(
    '/admin/merchant-vouchers',
    { params: { limit } },
  )

  return response.data.data.items
}

export async function createAdminMerchantVoucher(payload: AdminMerchantVoucherPayload) {
  const response = await apiClient.post<ApiEnvelope<{ voucher: MerchantVoucher }>>(
    '/admin/merchant-vouchers',
    payload,
  )

  return response.data.data.voucher
}

export async function voidAdminMerchantVoucher(voucherId: string, voidReason: string) {
  const response = await apiClient.post<ApiEnvelope<{ voucher: MerchantVoucher }>>(
    `/admin/merchant-vouchers/${voucherId}/void`,
    { voidReason },
  )

  return response.data.data.voucher
}
