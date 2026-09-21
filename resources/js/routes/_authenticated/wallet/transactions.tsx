import { createFileRoute } from '@tanstack/react-router'
import { TransactionsPage } from '@/features/wallet/transactions'

export const Route = createFileRoute('/_authenticated/wallet/transactions')({
  component: TransactionsPage,
})
