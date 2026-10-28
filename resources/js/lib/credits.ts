import { api } from '@/lib/api'
import { type PaymentRecord } from '@/types/payments'
import { type AuthUser } from '@/types/auth'
import { emptyPageMeta, type PageMeta } from '@/types/pagination'
import {
  type AdminCustomer,
  type CreditTransaction,
  type CustomerPricePackage,
  type PricingTier,
  type ProviderBalance,
  type SchedulerHealth,
  type SmsQuote,
  type WalletSnapshot,
} from '@/types/credits'

export const walletQueryKey = ['wallet'] as const
export const creditTransactionsQueryKey = ['credit-transactions'] as const
export const pricingTiersQueryKey = ['pricing-tiers'] as const
export const adminCustomersQueryKey = ['admin', 'customers'] as const
export const adminTransactionsQueryKey = ['admin', 'credit-transactions'] as const
export const adminPricingTiersQueryKey = ['admin', 'pricing-tiers'] as const
export const adminProviderBalanceQueryKey = ['admin', 'provider-balance'] as const

export function quoteQueryKey(quantity: number) {
  return ['quotes', quantity] as const
}

export function quantityInTier(quantity: number, tier: PricingTier): boolean {
  if (quantity < tier.minQuantity) {
    return false
  }

  if (tier.maxQuantity === null) {
    return true
  }

  return quantity <= tier.maxQuantity
}

export function quoteVolumeFromTiers(
  quantity: number,
  tiers: PricingTier[]
): { quantity: number; unitPriceTzs: number; totalTzs: number; tier: PricingTier } | null {
  if (quantity < 1 || tiers.length === 0) {
    return null
  }

  const tier =
    tiers.find((item) => quantityInTier(quantity, item)) ?? tiers[0] ?? null

  if (tier === null) {
    return null
  }

  return {
    quantity,
    unitPriceTzs: tier.unitPriceTzs,
    totalTzs: quantity * tier.unitPriceTzs,
    tier,
  }
}

export async function fetchWallet(): Promise<WalletSnapshot> {
  const { data } = await api.get<WalletSnapshot>('/api/wallet')
  return data
}

export async function fetchCreditTransactions(page = 1): Promise<{
  transactions: CreditTransaction[]
  meta: PageMeta
}> {
  const { data } = await api.get<{
    transactions: CreditTransaction[]
    meta?: PageMeta
  }>('/api/credit-transactions', { params: { page } })
  return {
    transactions: data.transactions,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function fetchPricingTiers(): Promise<PricingTier[]> {
  const { data } = await api.get<{ tiers: PricingTier[] }>('/api/pricing-tiers')
  return data.tiers
}

export async function createQuote(quantity: number): Promise<SmsQuote> {
  const { data } = await api.post<{ quote: SmsQuote }>('/api/quotes', {
    quantity,
  })
  return data.quote
}

export async function fetchAdminCustomers(
  q = '',
  page = 1
): Promise<{ customers: AdminCustomer[]; meta: PageMeta }> {
  const { data } = await api.get<{ customers: AdminCustomer[]; meta?: PageMeta }>(
    '/api/admin/customers',
    { params: { q: q || undefined, page } }
  )
  return {
    customers: data.customers,
    meta: data.meta ?? emptyPageMeta,
  }
}

export function adminCustomerPricesQueryKey(customerId: number | string) {
  return ['admin', 'customers', String(customerId), 'prices'] as const
}

export async function fetchAdminCustomerPrices(
  customerId: number
): Promise<{ packages: CustomerPricePackage[] }> {
  const { data } = await api.get<{ packages: CustomerPricePackage[] }>(
    `/api/admin/customers/${customerId}/prices`
  )
  return data
}

export async function saveAdminCustomerPrice(
  customerId: number,
  payload: {
    pricing_tier_id: number
    unit_price_tzs: number
    expires_at?: string | null
  }
): Promise<CustomerPricePackage> {
  const { data } = await api.post<{ package: CustomerPricePackage }>(
    `/api/admin/customers/${customerId}/prices`,
    payload
  )
  return data.package
}

export async function removeAdminCustomerPrice(
  customerId: number,
  priceId: number
): Promise<void> {
  await api.delete(`/api/admin/customers/${customerId}/prices/${priceId}`)
}

export async function grantCustomerCredits(
  customerId: number,
  quantity: number,
  note?: string,
  amountTzs?: number,
  unitPriceTzs?: number
): Promise<{ smsBalance: number; transaction: CreditTransaction }> {
  const { data } = await api.post<{
    smsBalance: number
    transaction: CreditTransaction
  }>(`/api/admin/customers/${customerId}/credits`, {
    quantity,
    note: note || undefined,
    amount_tzs: amountTzs,
    unit_price_tzs: unitPriceTzs,
  })
  return data
}

export async function fetchAdminTransactions(page = 1): Promise<{
  transactions: CreditTransaction[]
  meta: PageMeta
}> {
  const { data } = await api.get<{
    transactions: CreditTransaction[]
    meta?: PageMeta
  }>('/api/admin/credit-transactions', { params: { page } })
  return {
    transactions: data.transactions,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function reverseCreditTransaction(
  transactionId: number
): Promise<CreditTransaction> {
  const { data } = await api.post<{ transaction: CreditTransaction }>(
    `/api/admin/credit-transactions/${transactionId}/reversals`
  )
  return data.transaction
}

export async function fetchAdminPricingTiers(): Promise<{
  tiers: PricingTier[]
  providerUnitCostTzs: number
  purchaseRevenueTzs: number
  billedUsageTzs: number
  providerCostTzs: number
  estimatedMarginTzs: number
}> {
  const { data } = await api.get<{
    tiers: PricingTier[]
    providerUnitCostTzs: number
    purchaseRevenueTzs: number
    billedUsageTzs: number
    providerCostTzs: number
    estimatedMarginTzs: number
  }>('/api/admin/pricing-tiers')
  return data
}

export async function updatePricingTier(
  tierId: number,
  unitPriceTzs: number
): Promise<PricingTier> {
  const { data } = await api.patch<{ tier: PricingTier }>(
    `/api/admin/pricing-tiers/${tierId}`,
    { unit_price_tzs: unitPriceTzs }
  )
  return data.tier
}

export async function fetchAdminProviderBalance(): Promise<ProviderBalance> {
  const { data } = await api.get<ProviderBalance>('/api/admin/provider-balance')
  return data
}

export type ProviderDlrCheck = {
  messageId: string
  rawResponse: string
  dlrStatus: string
  dlrTime: string | null
  soscomStatus: string | null
  requestUrl: string
  driver: string
  matchedDelivery: boolean
  saved: boolean
  reportStatus: string | null
}

export async function checkAdminProviderDlr(
  messageId: string
): Promise<ProviderDlrCheck> {
  const { data } = await api.post<ProviderDlrCheck>(
    '/api/admin/provider-balance/dlr',
    { message_id: messageId }
  )
  return data
}

export async function runAdminDlrPoll(): Promise<
  SchedulerHealth & { output: string }
> {
  const { data } = await api.post<SchedulerHealth & { output: string }>(
    '/api/admin/provider-balance/dlr-poll'
  )
  return data
}

export const adminPaymentsQueryKey = ['admin', 'payments'] as const
export const paymentsQueryKey = ['payments'] as const

export async function fetchPayments(
  page = 1,
  perPage?: number
): Promise<{
  payments: PaymentRecord[]
  meta: PageMeta
}> {
  const { data } = await api.get<{
    payments: PaymentRecord[]
    meta?: PageMeta
  }>('/api/payments', { params: { page, per_page: perPage } })
  return {
    payments: data.payments,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function fetchPayment(id: number): Promise<{
  payment: PaymentRecord
  smsBalance: number | null
}> {
  const { data } = await api.get<{
    payment: PaymentRecord
    smsBalance: number | null
  }>(`/api/payments/${id}`)
  return data
}

export async function createPayment(payload: {
  quantity: number
  payer_reference: string
}): Promise<{ payment: PaymentRecord; smsBalance: number }> {
  const { data } = await api.post<{
    payment: PaymentRecord
    smsBalance: number
  }>('/api/payments', payload)
  return data
}

export async function fetchAdminPayments(
  page = 1,
  status?: string
): Promise<{
  payments: PaymentRecord[]
  meta: PageMeta
}> {
  const { data } = await api.get<{
    payments: PaymentRecord[]
    meta?: PageMeta
  }>('/api/admin/payments', {
    params: { page, status: status || undefined },
  })
  return {
    payments: data.payments,
    meta: data.meta ?? emptyPageMeta,
  }
}

export function withSmsBalance(user: AuthUser, smsBalance: number): AuthUser {
  if (!user.customer) {
    return user
  }

  return {
    ...user,
    customer: {
      ...user.customer,
      smsBalance,
    },
  }
}
