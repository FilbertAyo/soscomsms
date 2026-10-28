export const CREDIT_TRANSACTION_TYPES = [
  'purchase',
  'usage',
  'refund',
  'grant',
  'reversal',
] as const

export type CreditTransactionType = (typeof CREDIT_TRANSACTION_TYPES)[number]

export type PricingTier = {
  id: number
  minQuantity: number
  maxQuantity: number | null
  unitPriceTzs: number
  listUnitPriceTzs?: number
  specialPrice?: boolean
  label: string
}

export type SmsQuote = {
  quantity: number
  unitPriceTzs: number
  listUnitPriceTzs?: number
  specialPrice?: boolean
  totalTzs: number
  providerUnitCostTzs: number
  estimatedMarginTzs: number
  tier: PricingTier
}

export type CreditTransaction = {
  id: number
  type: CreditTransactionType
  quantity: number
  customerUnitPrice: number | null
  amountTzs: number | null
  balanceAfter: number
  reference: string
  description: string
  reversed: boolean
  createdAt: string
  customer: {
    id: number
    name: string | null
    email: string | null
    businessName: string | null
  } | null
}

export type SmsPackageQuote = {
  quantity: number
  unitPriceTzs: number
  listUnitPriceTzs?: number
  specialPrice?: boolean
  totalTzs: number
  popular: boolean
}

export type CustomerPricePackage = {
  tierId: number
  label: string
  minQuantity: number
  maxQuantity: number | null
  listUnitPriceTzs: number
  price: {
    id: number
    tierId: number
    unitPriceTzs: number
    expiresAt: string | null
    active: boolean
    updatedAt: string | null
  } | null
}

export type WalletSnapshot = {
  smsBalance: number
  welcomeCredits: number
  minPurchaseQuantity: number
  tiers: PricingTier[]
  packages: SmsPackageQuote[]
  recentTransactions: CreditTransaction[]
}

export type AdminCustomer = {
  id: number
  name: string | null
  email: string | null
  businessName: string | null
  phone: string | null
  status: string
  smsBalance: number
  sentThisMonth: number
  campaignCount?: number | null
  createdAt: string | null
}

export type ProviderBalance = {
  driver: 'fake' | 'mobishastra'
  name: string
  smsBalance: number
  customerCredits: number
  realSmsBalance: number
  parsed: boolean
  parseError: string | null
  rawResponse: string
  lowBalanceThreshold: number
  lowBalance: boolean
  staticIp: string | null
  staticIpWhitelistedAt: string | null
  baseUrl: string | null
  profileId: string | null
  countryCode: string | null
  credentialsConfirmedAt: string | null
  checkedAt: string | null
} & SchedulerHealth

export type SchedulerHealth = {
  dlrPoll: {
    at: string
    checked: number
    updated: number
    errors: number
    waiting: number
    seconds?: number
  } | null
  dlrPollStartedAt: string | null
  queueHeartbeatAt: string | null
}
