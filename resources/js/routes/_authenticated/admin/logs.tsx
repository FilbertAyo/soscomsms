import { createFileRoute } from '@tanstack/react-router'
import { AdminLogs } from '@/features/admin/logs'

export const Route = createFileRoute('/_authenticated/admin/logs')({
  component: AdminLogs,
})
