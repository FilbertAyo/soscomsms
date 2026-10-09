import { api } from '@/lib/api'
import {
  type AdminApiKey,
  type AdminApiLog,
  type AdminWebhookDelivery,
  type ApiKeyRecord,
  type CreatedApiKey,
} from '@/types/api-keys'
import { emptyPageMeta, type PageMeta } from '@/types/pagination'

export const apiKeysQueryKey = ['api-keys'] as const
export const adminApiUsageQueryKey = ['admin', 'api-usage'] as const

export async function fetchApiKeys(): Promise<ApiKeyRecord[]> {
  const { data } = await api.get<{ keys: ApiKeyRecord[] }>('/api/api-keys')
  return data.keys
}

export async function createApiKey(name: string): Promise<CreatedApiKey> {
  const { data } = await api.post<CreatedApiKey>('/api/api-keys', { name })
  return data
}

export async function revokeApiKey(id: number): Promise<ApiKeyRecord> {
  const { data } = await api.post<{ apiKey: ApiKeyRecord }>(
    `/api/api-keys/${id}/revoke`
  )
  return data.apiKey
}

export async function fetchAdminApiRequests(
  page = 1,
  q = ''
): Promise<{ logs: AdminApiLog[]; meta: PageMeta }> {
  const { data } = await api.get<{ logs: AdminApiLog[]; meta?: PageMeta }>(
    '/api/admin/api-usage/requests',
    { params: { page, q: q || undefined } }
  )
  return { logs: data.logs, meta: data.meta ?? emptyPageMeta }
}

export async function fetchAdminApiKeys(
  page = 1,
  q = ''
): Promise<{ keys: AdminApiKey[]; meta: PageMeta }> {
  const { data } = await api.get<{ keys: AdminApiKey[]; meta?: PageMeta }>(
    '/api/admin/api-usage/keys',
    { params: { page, q: q || undefined } }
  )
  return { keys: data.keys, meta: data.meta ?? emptyPageMeta }
}

export async function fetchAdminWebhookDeliveries(
  page = 1,
  q = ''
): Promise<{ webhookDeliveries: AdminWebhookDelivery[]; meta: PageMeta }> {
  const { data } = await api.get<{
    webhookDeliveries: AdminWebhookDelivery[]
    meta?: PageMeta
  }>('/api/admin/api-usage/webhook-deliveries', {
    params: { page, q: q || undefined },
  })
  return {
    webhookDeliveries: data.webhookDeliveries,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value)
    return true
  } catch {
    return false
  }
}
