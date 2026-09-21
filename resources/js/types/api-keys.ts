import { type WebhookDeliveryRecord } from '@/types/webhooks'

export type ApiKeyRecord = {
  id: number
  name: string
  prefix: string
  masked: string
  status: 'active' | 'revoked'
  lastUsedAt: string | null
  createdAt: string | null
  revokedAt: string | null
}

export type CreatedApiKey = {
  key: string
  apiKey: ApiKeyRecord
}

export type AdminApiKey = ApiKeyRecord & {
  customerId: number
  customerEmail: string | null
}

export type AdminApiLog = {
  id: number
  customerId: number | null
  customerEmail: string | null
  keyName: string | null
  keyPrefix: string | null
  method: string
  path: string
  statusCode: number
  ip: string | null
  requestId: string
  campaignId: number | null
  error: string | null
  createdAt: string | null
}

export type AdminWebhookDelivery = WebhookDeliveryRecord


