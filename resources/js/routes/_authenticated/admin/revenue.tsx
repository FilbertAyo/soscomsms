import { createFileRoute } from '@tanstack/react-router'
import { AdminRevenue } from '@/features/admin/revenue'

export const Route = createFileRoute('/_authenticated/admin/revenue')({
  component: AdminRevenue,
})
