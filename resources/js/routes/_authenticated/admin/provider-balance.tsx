import { createFileRoute } from '@tanstack/react-router'
import { AdminProviderBalance } from '@/features/admin/provider-balance'

export const Route = createFileRoute('/_authenticated/admin/provider-balance')({
  component: AdminProviderBalance,
})
