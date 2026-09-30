export const SENDER_ID_SOURCES = ['generic', 'request', 'admin'] as const

export type SenderIdSource = (typeof SENDER_ID_SOURCES)[number]

export const SENDER_ID_STATUSES = ['active', 'inactive'] as const

export type SenderIdStatus = (typeof SENDER_ID_STATUSES)[number]

export const SENDER_ID_REQUEST_STATUSES = [
  'submitted',
  'under_review',
  'submitted_to_provider',
  'approved',
  'rejected',
] as const

export type SenderIdRequestStatus = (typeof SENDER_ID_REQUEST_STATUSES)[number]

export type SenderIdRecord = {
  id: number
  name: string
  status: SenderIdStatus
  source: SenderIdSource
  customerId: number | null
  purpose: string | null
  organization: string | null
  createdAt: string | null
}

export type SenderIdGrantCustomer = {
  id: number
  name: string | null
  email: string | null
  businessName: string | null
}

export type AdminSenderIdCatalogRecord = {
  id: number
  name: string
  status: SenderIdStatus
  source: SenderIdSource
  availableToAll: boolean
  customerId: number | null
  campaignCount: number
  grants: SenderIdGrantCustomer[]
  createdAt: string | null
}

export type SenderIdRequestRecord = {
  id: number
  name: string
  purpose: string
  organization: string
  description: string | null
  status: SenderIdRequestStatus
  currentStep: string
  timelineIndex: number
  submittedToProviderAt: string | null
  rejectionReason: string | null
  createdAt: string | null
  senderIdId: number | null
  customerId?: number
  customerName?: string | null
  customerBusiness?: string | null
  customerEmail?: string | null
  providerReference?: string | null
  notes?: string | null
  resolvedAt?: string | null
}

export const CUSTOMER_REQUEST_STEPS = [
  { id: 'submitted', label: 'Request submitted' },
  { id: 'under_review', label: 'Under review' },
  { id: 'submitted_to_provider', label: 'Being approved' },
  { id: 'approved', label: 'Approved' },
  { id: 'active', label: 'Active' },
] as const

export const ADMIN_REQUEST_STEPS = [
  { id: 'submitted', label: 'Customer Requested' },
  { id: 'submitted_to_provider', label: 'Submitted to Provider' },
  { id: 'approved', label: 'Approved' },
  { id: 'active', label: 'Activated' },
] as const

export function adminTimelineIndex(request: SenderIdRequestRecord): number {
  switch (request.status) {
    case 'submitted':
    case 'under_review':
      return 0
    case 'submitted_to_provider':
      return 1
    case 'approved':
      return 3
    case 'rejected':
      return request.submittedToProviderAt ? 1 : 0
    default: {
      const exhaustive: never = request.status
      return exhaustive
    }
  }
}
