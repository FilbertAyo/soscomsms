import { type AdminCustomer } from '@/types/credits'
import { type PageMeta } from '@/types/pagination'

export type AdminAuditLog = {
  id: number
  source: 'audit'
  action: string
  level: string
  message: string
  actorName: string | null
  actorEmail: string | null
  customerEmail: string | null
  createdAt: string | null
}

export type AdminProviderLog = {
  id: number
  source: 'provider'
  action: string
  status: string
  httpStatus: number | null
  campaignId: number | null
  campaignName: string | null
  providerName: string | null
  senderId: string | null
  phone: string | null
  countryCode: string | null
  providerReply: string | null
  diagnosis: string | null
  error: string | null
  message: string
  createdAt: string | null
}

export type AdminOverview = {
  customers: number
  smsTraffic: number
  smsThisMonth: number
  failedDeliveries: number
  purchaseRevenueTzs: number
  billedUsageTzs: number
  providerCostTzs: number
  estimatedMarginTzs: number
  recentCustomers: AdminCustomer[]
  recentAuditLogs: AdminAuditLog[]
}

export type AdminTraffic = {
  smsTraffic: number
  smsThisMonth: number
  failedDeliveries: number
  campaigns: AdminTrafficCampaign[]
  meta?: PageMeta
}

export type AdminTrafficCampaign = {
  id: number
  name: string
  senderId: string
  status: string
  recipients: number
  sent: number
  delivered: number
  failed: number
  totalSms: number
  customerName: string | null
  customerEmail: string | null
  customerBusiness: string | null
  createdAt: string | null
}

export type AdminDeliveryReport = {
  id: number
  phone: string | null
  campaignId: number | null
  campaignName: string | null
  senderId: string | null
  message: string | null
  status: string | null
  providerMessageId: string | null
  sentAt: string | null
  deliveredAt: string | null
  customerName: string | null
  customerEmail: string | null
}

export type ProductionCheck = {
  id: string
  label: string
  ready: boolean
  detail: string
}

export type ProductionSnapshot = {
  environment: string
  appUrl: string
  expectedAppUrl: string
  https: boolean
  timezone: string
  queueConnection: string
  queueHeartbeatAt: string | null
  queuePendingJobs: number
  queueFailedJobs: number
  mailer: string
  logChannel: string
  database: string
  driver: 'fake' | 'mobishastra'
  localUsesFake: boolean
  canSendViaMobishastra: boolean
  blockReason: string | null
  goLiveReady: boolean
  checks: ProductionCheck[]
  provider: {
    id: number
    name: string
    driver: string
    staticIp: string | null
    staticIpWhitelistedAt: string | null
    baseUrl: string | null
    profileId: string | null
    passwordSet: boolean
    countryCode: string
    credentialsConfirmedAt: string | null
    lowBalanceThreshold: number
    lastBalance: number | null
    lastBalanceAt: string | null
    lowBalanceAlertedAt: string | null
  }
  lastBackupAt: string | null
  backupKeepDays: number
  message?: string
}
