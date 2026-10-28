export type PaymentStatus = 'pending' | 'paid' | 'rejected' | 'failed' | 'expired'

export type PaymentRecord = {
  id: number
  quantity: number
  unitPriceTzs: number
  listUnitPriceTzs: number | null
  specialPrice: boolean
  amountTzs: number
  method: 'lipa_namba' | 'snippe'
  payerReference: string
  provider: string | null
  feeTzs: number | null
  netTzs: number | null
  snippeReference: string | null
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
