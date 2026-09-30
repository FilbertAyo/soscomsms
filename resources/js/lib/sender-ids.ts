import { api } from '@/lib/api'
import {
  type AdminSenderIdCatalogRecord,
  type SenderIdRecord,
  type SenderIdRequestRecord,
} from '@/types/sender-ids'

export const usableSenderIdsQueryKey = ['sender-ids'] as const
export const senderIdRequestsQueryKey = ['sender-id-requests'] as const
export const adminSenderIdRequestsQueryKey = [
  'admin',
  'sender-id-requests',
] as const
export const adminSenderIdCatalogQueryKey = [
  'admin',
  'sender-ids',
] as const

export function senderIdRequestQueryKey(id: number | string) {
  return ['sender-id-requests', String(id)] as const
}

export async function fetchUsableSenderIds(): Promise<SenderIdRecord[]> {
  const { data } = await api.get<{ senderIds: SenderIdRecord[] }>(
    '/api/sender-ids'
  )
  return data.senderIds
}

export async function assertSenderId(name: string): Promise<SenderIdRecord> {
  const { data } = await api.post<{ senderId: SenderIdRecord }>(
    '/api/sender-ids/assert',
    { sender_id: name }
  )
  return data.senderId
}

export async function fetchSenderIdRequests(): Promise<SenderIdRequestRecord[]> {
  const { data } = await api.get<{ requests: SenderIdRequestRecord[] }>(
    '/api/sender-id-requests'
  )
  return data.requests
}

export async function fetchSenderIdRequest(
  id: number | string
): Promise<SenderIdRequestRecord> {
  const { data } = await api.get<{ request: SenderIdRequestRecord }>(
    `/api/sender-id-requests/${id}`
  )
  return data.request
}

export async function createSenderIdRequest(payload: {
  name: string
  purpose: string
  organization: string
  description?: string
}): Promise<SenderIdRequestRecord> {
  const { data } = await api.post<{ request: SenderIdRequestRecord }>(
    '/api/sender-id-requests',
    payload
  )
  return data.request
}

export async function fetchAdminSenderIdRequests(): Promise<
  SenderIdRequestRecord[]
> {
  const { data } = await api.get<{ requests: SenderIdRequestRecord[] }>(
    '/api/admin/sender-id-requests'
  )
  return data.requests
}

export async function reviewSenderIdRequest(
  id: number
): Promise<SenderIdRequestRecord> {
  const { data } = await api.post<{ request: SenderIdRequestRecord }>(
    `/api/admin/sender-id-requests/${id}/review`
  )
  return data.request
}

export async function submitSenderIdRequestToProvider(
  id: number,
  payload: {
    submitted_to_provider_at?: string
    provider_reference?: string
    notes?: string
  }
): Promise<SenderIdRequestRecord> {
  const { data } = await api.post<{ request: SenderIdRequestRecord }>(
    `/api/admin/sender-id-requests/${id}/provider-submissions`,
    payload
  )
  return data.request
}

export async function approveSenderIdRequest(
  id: number
): Promise<SenderIdRequestRecord> {
  const { data } = await api.post<{ request: SenderIdRequestRecord }>(
    `/api/admin/sender-id-requests/${id}/approval`
  )
  return data.request
}

export async function rejectSenderIdRequest(
  id: number,
  rejectionReason: string
): Promise<SenderIdRequestRecord> {
  const { data } = await api.post<{ request: SenderIdRequestRecord }>(
    `/api/admin/sender-id-requests/${id}/rejection`,
    { rejection_reason: rejectionReason }
  )
  return data.request
}

export async function fetchAdminSenderIdCatalog(): Promise<
  AdminSenderIdCatalogRecord[]
> {
  const { data } = await api.get<{ senderIds: AdminSenderIdCatalogRecord[] }>(
    '/api/admin/sender-ids'
  )
  return data.senderIds
}

export async function createAdminSenderId(payload: {
  name: string
  generic: boolean
  available_to_all: boolean
}): Promise<AdminSenderIdCatalogRecord> {
  const { data } = await api.post<{ senderId: AdminSenderIdCatalogRecord }>(
    '/api/admin/sender-ids',
    payload
  )
  return data.senderId
}

export async function updateAdminSenderId(
  id: number,
  payload: {
    generic?: boolean
    available_to_all?: boolean
    status?: 'active' | 'inactive'
  }
): Promise<AdminSenderIdCatalogRecord> {
  const { data } = await api.patch<{ senderId: AdminSenderIdCatalogRecord }>(
    `/api/admin/sender-ids/${id}`,
    payload
  )
  return data.senderId
}

export async function grantAdminSenderId(
  id: number,
  customerId: number
): Promise<AdminSenderIdCatalogRecord> {
  const { data } = await api.post<{ senderId: AdminSenderIdCatalogRecord }>(
    `/api/admin/sender-ids/${id}/grants`,
    { customer_id: customerId }
  )
  return data.senderId
}

export async function revokeAdminSenderId(
  id: number,
  customerId: number
): Promise<AdminSenderIdCatalogRecord> {
  const { data } = await api.delete<{ senderId: AdminSenderIdCatalogRecord }>(
    `/api/admin/sender-ids/${id}/grants/${customerId}`
  )
  return data.senderId
}

export async function deleteAdminSenderId(id: number): Promise<void> {
  await api.delete(`/api/admin/sender-ids/${id}`)
}
