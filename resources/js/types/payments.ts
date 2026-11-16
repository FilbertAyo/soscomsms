export type PaymentStatus = 'pending' | 'paid' | 'rejected' | 'failed' | 'expired'

export type PaymentMethodName =
  | 'lipa_namba'
  | 'snippe'
  | 'cash'
  | 'bank'
  | 'manual_mobile'

export type ManualPaymentMethod = 'cash' | 'bank' | 'manual_mobile'

export type PurchaseReceipt = {
  receiptNumber: string
  issuedAt: string | null
  issuedAtLabel: string | null
  status: 'paid' | 'reversed'
  issuer: {
    company: string
    product: string
    website: string
    email: string
  }
  billTo: {
    name: string | null
    businessName: string | null
    email: string | null
    phone: string | null
  }
  line: {
    description: string
    quantity: number
    unitPriceTzs: number
    listUnitPriceTzs: number | null
    specialPrice: boolean
    amountTzs: number
  }
  paymentMethod: string
  paymentMethodLabel: string
  transactionId: string | null
  internalReference: string
  payerReference: string | null
  balanceAfter: number | null
  smsAdded: number
  note: string | null
}

export type PaymentRecord = {
  id: number
  quantity: number
  unitPriceTzs: number
  listUnitPriceTzs: number | null
  specialPrice: boolean
  amountTzs: number
  method: PaymentMethodName
  payerReference: string
  provider: string | null
  feeTzs: number | null
  netTzs: number | null
  snippeReference: string | null
  externalReference: string | null
  status: PaymentStatus
  creditTransactionId: number | null
  rejectionReason: string | null
  adminNote: string | null
  expiresAt: string | null
  confirmedAt: string | null
  rejectedAt: string | null
  createdAt: string | null
  customer: {
    id: number
    name: string | null
    email: string | null
    businessName: string | null
  } | null
}
