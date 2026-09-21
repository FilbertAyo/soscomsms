import { createFileRoute } from '@tanstack/react-router'
import { AdminTransactions } from '@/features/admin/transactions'

export const Route = createFileRoute('/_authenticated/admin/transactions')({
  component: AdminTransactions,
})
