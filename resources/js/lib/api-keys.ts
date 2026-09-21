import { api } from '@/lib/api'
import {
  type AdminApiKey,
  type AdminApiLog,
  type AdminWebhookDelivery,
  type ApiKeyRecord,
  type CreatedApiKey,
} from '@/types/api-keys'

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

export async function fetchAdminApiUsage(): Promise<{
  logs: AdminApiLog[]
  keys: AdminApiKey[]
  webhookDeliveries: AdminWebhookDelivery[]
}> {
  const { data } = await api.get<{
    logs: AdminApiLog[]
    keys: AdminApiKey[]
    webhookDeliveries: AdminWebhookDelivery[]
  }>('/api/admin/api-usage')
  return data
}

export async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value)
    return true
  } catch {
    return false
  }
}
