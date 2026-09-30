import { createFileRoute } from '@tanstack/react-router'
import { AdminAuditLogs } from '@/features/admin/logs'

export const Route = createFileRoute('/_authenticated/admin/logs/audit')({
  component: AdminAuditLogs,
})
