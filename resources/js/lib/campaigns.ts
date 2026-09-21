import { api } from '@/lib/api'
import {
  type Campaign,
  type CampaignPayload,
  type CampaignPreview,
  type CampaignRecipient,
  type DeliveryReport,
} from '@/types/campaigns'
import { emptyPageMeta, type PageMeta } from '@/types/pagination'

export const campaignsQueryKey = ['campaigns'] as const
export const dashboardStatsQueryKey = ['dashboard-stats'] as const

export function campaignQueryKey(id: number | string) {
  return ['campaigns', String(id)] as const
}

export function previewQueryKey(payload: object) {
  return ['sms-preview', payload] as const
}

export type DashboardStats = {
  totalSms: number
  delivered: number
  failed: number
  campaignCount: number
}

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await api.get<DashboardStats>('/api/dashboard/stats')
  return data
}

export async function previewCampaign(
  payload: Omit<CampaignPayload, 'sender_id'> & { sender_id?: string }
): Promise<CampaignPreview> {
  const { data } = await api.post<CampaignPreview>('/api/sms/preview', payload)
  return data
}

export async function createCampaign(
  payload: CampaignPayload
): Promise<{ campaign: Campaign; smsBalance: number }> {
  const { data } = await api.post<{
    campaign: Campaign
    smsBalance: number
  }>('/api/campaigns', payload)
  return data
}

export async function fetchCampaigns(options?: {
  status?: string
  page?: number
  perPage?: number
}): Promise<{ campaigns: Campaign[]; meta: PageMeta }> {
  const { data } = await api.get<{ campaigns: Campaign[]; meta?: PageMeta }>(
    '/api/campaigns',
    {
      params: {
        status: options?.status || undefined,
        page: options?.page || undefined,
        per_page: options?.perPage || undefined,
      },
    }
  )
  return {
    campaigns: data.campaigns,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function fetchCampaign(
  id: number | string,
  page?: number
): Promise<Campaign> {
  const { data } = await api.get<{ campaign: Campaign }>(
    `/api/campaigns/${id}`,
    { params: { page: page || undefined } }
  )
  return data.campaign
}

export async function fetchCampaignRecipients(
  id: number | string,
  page = 1
): Promise<{ recipients: CampaignRecipient[]; meta: PageMeta }> {
  const campaign = await fetchCampaign(id, page)
  return {
    recipients: campaign.recipientRows ?? [],
    meta: campaign.recipientMeta ?? emptyPageMeta,
  }
}

export async function cancelCampaign(
  id: number | string
): Promise<{ campaign: Campaign; smsBalance: number }> {
  const { data } = await api.post<{
    campaign: Campaign
    smsBalance: number
  }>(`/api/campaigns/${id}/cancel`)
  return data
}

export const deliveryReportsQueryKey = ['delivery-reports'] as const

export function deliveryReportsQuery(filters: {
  campaignId?: string
  senderId?: string
  status?: string
  q?: string
  date?: string
  page?: number
}) {
  return [...deliveryReportsQueryKey, filters] as const
}

export async function fetchDeliveryReports(filters: {
  campaignId?: string
  senderId?: string
  status?: string
  q?: string
  date?: string
  page?: number
  perPage?: number
}): Promise<{ reports: DeliveryReport[]; meta: PageMeta }> {
  const { data } = await api.get<{ reports: DeliveryReport[]; meta?: PageMeta }>(
    '/api/delivery-reports',
    {
      params: {
        campaign_id: filters.campaignId || undefined,
        sender_id: filters.senderId || undefined,
        status: filters.status || undefined,
        q: filters.q || undefined,
        date: filters.date || undefined,
        page: filters.page || undefined,
        per_page: filters.perPage || 50,
      },
    }
  )
  return {
    reports: data.reports,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function exportDeliveryReports(filters: {
  campaignId?: string
  senderId?: string
  status?: string
  q?: string
  date?: string
}): Promise<void> {
  const { data } = await api.get<Blob>('/api/delivery-reports/export', {
    params: {
      campaign_id: filters.campaignId || undefined,
      sender_id: filters.senderId || undefined,
      status: filters.status || undefined,
      q: filters.q || undefined,
      date: filters.date || undefined,
    },
    responseType: 'blob',
  })
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = 'delivery-reports.csv'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
