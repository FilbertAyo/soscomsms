import { createFileRoute } from '@tanstack/react-router'
import { AdminCustomers } from '@/features/admin/customers'

export const Route = createFileRoute('/_authenticated/admin/customers/')({
  component: AdminCustomers,
})
