import { createFileRoute } from '@tanstack/react-router'
import { AdminApiUsage } from '@/features/admin/api-usage'

export const Route = createFileRoute('/_authenticated/admin/api-usage')({
  component: AdminApiUsage,
})
