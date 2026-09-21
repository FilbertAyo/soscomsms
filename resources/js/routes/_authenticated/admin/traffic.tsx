import { createFileRoute } from '@tanstack/react-router'
import { AdminTraffic } from '@/features/admin/traffic'

export const Route = createFileRoute('/_authenticated/admin/traffic')({
  component: AdminTraffic,
})
