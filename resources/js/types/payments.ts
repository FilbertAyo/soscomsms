export type PaymentStatus = 'pending' | 'paid' | 'rejected' | 'expired'

export type PaymentRecord = {
  id: number
  quantity: number
  unitPriceTzs: number
  amountTzs: number
  method: 'lipa_namba'
  lipaNamba: string
  payerReference: string
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
