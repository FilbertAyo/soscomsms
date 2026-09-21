export const WEBHOOK_EVENTS = [
  'delivery.updated',
  'campaign.completed',
] as const

export type WebhookEvent = (typeof WEBHOOK_EVENTS)[number]

export type WebhookRecord = {
  id: number
  name: string
  url: string
  events: WebhookEvent[]
  enabled: boolean
  createdAt: string | null
  updatedAt: string | null
}

export type CreatedWebhook = {
  webhook: WebhookRecord
  secret: string
}

export type WebhookDeliveryRecord = {
  id: number
  webhookId: number
  webhookName: string | null
  event: WebhookEvent
  status: 'pending' | 'delivered' | 'failed'
  attempt: number
  httpStatus: number | null
  error: string | null
  campaignId: number | null
  nextRetryAt: string | null
  deliveredAt: string | null
  createdAt: string | null
  customerId?: number | null
  customerEmail?: string | null
  url?: string | null
}
