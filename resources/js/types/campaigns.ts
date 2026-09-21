export const CAMPAIGN_STATUSES = [
  'draft',
  'queued',
  'scheduled',
  'sending',
  'completed',
  'partially_delivered',
  'failed',
  'cancelled',
] as const

export type CampaignStatus = (typeof CAMPAIGN_STATUSES)[number]

export const CAMPAIGN_RECIPIENT_STATUSES = [
  'pending',
  'queued',
  'sent',
  'delivered',
  'failed',
  'rejected',
  'unknown',
] as const

export type CampaignRecipientStatus =
  (typeof CAMPAIGN_RECIPIENT_STATUSES)[number]

export type CampaignRecipient = {
  id: number
  phone: string
  status: CampaignRecipientStatus
  contactId: number | null
  segmentCount: number
  providerMessageId: string | null
  deliveredAt: string | null
}

export type Campaign = {
  id: number
  name: string
  senderId: string
  status: CampaignStatus
  message: string | null
  encoding: 'gsm' | 'unicode' | null
  characterCount: number | null
  segmentsPerRecipient: number | null
  recipientPhone: string | null
  recipients: number
  sent: number
  delivered: number
  failed: number
  totalSms: number
  scheduledAt: string | null
  queuedAt: string | null
  createdAt: string | null
  recipientRows?: CampaignRecipient[]
  recipientMeta?: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
  }
}

export type CampaignPreview = {
  encoding: 'gsm' | 'unicode'
  characterCount: number
  segmentsPerRecipient: number
  singleLimit: number
  concatLimit: number
  recipientCount: number
  invalidCount: number
  duplicateCount: number
  invalidNumbers: string[]
  totalSms: number
  unitPriceTzs: number
  costTzs: number
  smsBalance: number
  balanceAfter: number
  enoughCredits: boolean
}

export type CampaignPayload = {
  source: 'numbers' | 'contacts' | 'group' | 'upload'
  message: string
  sender_id: string
  numbers?: string[]
  contact_ids?: number[]
  group_id?: number
  name?: string
  scheduled_at?: string
}

export type DeliveryReport = {
  id: number
  phone: string | null
  campaignId: number | null
  campaignName: string | null
  senderId: string | null
  message: string | null
  status: CampaignRecipientStatus | string | null
  providerMessageId: string | null
  sentAt: string | null
  deliveredAt: string | null
}
