export const SENDER_ID_SOURCES = ['generic', 'request', 'admin'] as const

export type SenderIdSource = (typeof SENDER_ID_SOURCES)[number]

export const SENDER_ID_STATUSES = ['active', 'inactive'] as const

export type SenderIdStatus = (typeof SENDER_ID_STATUSES)[number]

export const SENDER_ID_REQUEST_STATUSES = [
  'submitted',
  'under_review',
  'submitted_to_provider',
  'needs_resubmission',
  'approved',
  'rejected',
] as const

export type SenderIdRequestStatus = (typeof SENDER_ID_REQUEST_STATUSES)[number]

export const SENDER_ID_USE_CASES = [
  { value: 'transactional', label: 'Transactional' },
  { value: 'promotional', label: 'Promotional' },
  { value: 'both', label: 'Transactional & promotional' },
] as const

export type SenderIdUseCase = (typeof SENDER_ID_USE_CASES)[number]['value']

export const IDENTITY_DOCUMENT_TYPES = [
  { value: 'national_id', label: 'National ID (NIDA)' },
  { value: 'passport', label: 'Passport' },
  { value: 'voters_id', label: "Voter's ID" },
  { value: 'driving_licence', label: 'Driving licence' },
  { value: 'brela_certificate', label: 'BRELA certificate' },
  { value: 'tin_certificate', label: 'TIN certificate' },
  { value: 'business_licence', label: 'Business licence' },
] as const

export type IdentityDocumentType =
  (typeof IDENTITY_DOCUMENT_TYPES)[number]['value']

export function senderIdUseCaseLabel(value: string | null | undefined): string {
  if (!value) {
    return ''
  }

  return SENDER_ID_USE_CASES.find((item) => item.value === value)?.label ?? value
}

export function identityDocumentTypeLabel(
  value: string | null | undefined
): string {
  if (!value) {
    return ''
  }

  return (
    IDENTITY_DOCUMENT_TYPES.find((item) => item.value === value)?.label ?? value
  )
}

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
  websiteUrl: string | null
  description: string | null
  sampleContent: string | null
  identityDocumentType: string | null
  identityDocumentName: string | null
  hasIdentityDocument: boolean
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
    case 'needs_resubmission':
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
