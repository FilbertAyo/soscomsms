import { api } from '@/lib/api'
import { type AdminCustomer } from '@/types/credits'
import { emptyPageMeta, type PageMeta } from '@/types/pagination'
import {
  type AdminAuditLog,
  type AdminDeliveryReport,
  type AdminOverview,
  type AdminProviderLog,
  type AdminTraffic,
  type ProductionSnapshot,
} from '@/types/admin'

export const adminOverviewQueryKey = ['admin', 'overview'] as const
export const adminTrafficQueryKey = ['admin', 'traffic'] as const
export const adminDeliveryReportsQueryKey = ['admin', 'delivery-reports'] as const
export const adminLogsQueryKey = ['admin', 'logs'] as const

export function adminLogsPageQueryKey(auditPage: number, providerPage: number) {
  return [...adminLogsQueryKey, auditPage, providerPage] as const
}

export function adminCustomersSearchQueryKey(q: string) {
  return ['admin', 'customers', q] as const
}

export async function fetchAdminOverview(): Promise<AdminOverview> {
  const { data } = await api.get<AdminOverview>('/api/admin/overview')
  return data
}

export async function fetchAdminTraffic(page = 1): Promise<AdminTraffic> {
  const { data } = await api.get<AdminTraffic>('/api/admin/traffic', {
    params: { page },
  })
  return {
    ...data,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function fetchAdminDeliveryReports(page = 1): Promise<{
  reports: AdminDeliveryReport[]
  meta: PageMeta
}> {
  const { data } = await api.get<{ reports: AdminDeliveryReport[]; meta?: PageMeta }>(
    '/api/admin/delivery-reports',
    { params: { page } }
  )
  return {
    reports: data.reports,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function fetchAdminLogs(
  auditPage = 1,
  providerPage = 1
): Promise<{
  auditLogs: AdminAuditLog[]
  providerLogs: AdminProviderLog[]
  auditMeta: PageMeta
  providerMeta: PageMeta
}> {
  const { data } = await api.get<{
    auditLogs: AdminAuditLog[]
    providerLogs: AdminProviderLog[]
    auditMeta?: PageMeta
    providerMeta?: PageMeta
  }>('/api/admin/logs', {
    params: {
      audit_page: auditPage,
      provider_page: providerPage,
      per_page: 15,
    },
  })
  return {
    auditLogs: data.auditLogs,
    providerLogs: data.providerLogs,
    auditMeta: data.auditMeta ?? emptyPageMeta,
    providerMeta: data.providerMeta ?? emptyPageMeta,
  }
}

export async function fetchAdminCustomer(id: number): Promise<AdminCustomer> {
  const { data } = await api.get<{ customer: AdminCustomer }>(
    `/api/admin/customers/${id}`
  )
  return data.customer
}

export async function suspendAdminCustomer(id: number): Promise<AdminCustomer> {
  const { data } = await api.post<{ customer: AdminCustomer }>(
    `/api/admin/customers/${id}/suspension`
  )
  return data.customer
}

export async function unsuspendAdminCustomer(id: number): Promise<AdminCustomer> {
  const { data } = await api.post<{ customer: AdminCustomer }>(
    `/api/admin/customers/${id}/unsuspension`
  )
  return data.customer
}

export const adminProductionQueryKey = ['admin', 'production'] as const

export async function fetchAdminProduction(): Promise<ProductionSnapshot> {
  const { data } = await api.get<ProductionSnapshot>('/api/admin/production')
  return data
}

export async function updateAdminProduction(payload: {
  static_ip?: string
  base_url?: string
  country_code?: string
  low_balance_threshold?: number
}): Promise<ProductionSnapshot> {
  const { data } = await api.patch<ProductionSnapshot>(
    '/api/admin/production',
    payload
  )
  return data
}

export async function confirmAdminProviderWhitelist(): Promise<ProductionSnapshot> {
  const { data } = await api.post<ProductionSnapshot>(
    '/api/admin/production/whitelist'
  )
  return data
}

export async function confirmAdminProviderAccount(): Promise<ProductionSnapshot> {
  const { data } = await api.post<ProductionSnapshot>(
    '/api/admin/production/account'
  )
  return data
}

export async function runAdminDatabaseBackup(): Promise<ProductionSnapshot> {
  const { data } = await api.post<ProductionSnapshot>(
    '/api/admin/production/backups'
  )
  return data
}
