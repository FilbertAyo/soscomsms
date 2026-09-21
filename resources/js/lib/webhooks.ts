import { api } from '@/lib/api'
import {
  WEBHOOK_EVENTS,
  type CreatedWebhook,
  type WebhookDeliveryRecord,
  type WebhookEvent,
  type WebhookRecord,
} from '@/types/webhooks'

export const webhooksQueryKey = ['webhooks'] as const
export { WEBHOOK_EVENTS }

export function webhookDeliveriesQueryKey(id: number) {
  return ['webhooks', id, 'deliveries'] as const
}

export async function fetchWebhooks(): Promise<WebhookRecord[]> {
  const { data } = await api.get<{ webhooks: WebhookRecord[] }>('/api/webhooks')
  return data.webhooks
}

export async function createWebhook(payload: {
  name: string
  url: string
  events: WebhookEvent[]
}): Promise<CreatedWebhook> {
  const { data } = await api.post<CreatedWebhook>('/api/webhooks', payload)
  return data
}

export async function updateWebhook(
  id: number,
  payload: Partial<{
    name: string
    url: string
    events: WebhookEvent[]
    enabled: boolean
  }>
): Promise<WebhookRecord> {
  const { data } = await api.patch<{ webhook: WebhookRecord }>(
    `/api/webhooks/${id}`,
    payload
  )
  return data.webhook
}

export async function deleteWebhook(id: number): Promise<void> {
  await api.delete(`/api/webhooks/${id}`)
}

export async function rotateWebhookSecret(id: number): Promise<CreatedWebhook> {
  const { data } = await api.post<CreatedWebhook>(
    `/api/webhooks/${id}/rotate-secret`
  )
  return data
}

export async function fetchWebhookDeliveries(
  id: number
): Promise<WebhookDeliveryRecord[]> {
  const { data } = await api.get<{ deliveries: WebhookDeliveryRecord[] }>(
    `/api/webhooks/${id}/deliveries`
  )
  return data.deliveries
}

export function webhookEventLabel(event: WebhookEvent): string {
  switch (event) {
    case 'delivery.updated':
      return 'Delivery updated'
    case 'campaign.completed':
      return 'Campaign completed'
    default: {
      const neverEvent: never = event
      return neverEvent
    }
  }
}
