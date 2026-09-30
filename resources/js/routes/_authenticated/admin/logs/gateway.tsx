import { createFileRoute } from '@tanstack/react-router'
import { AdminGatewayLogs } from '@/features/admin/logs'

export const Route = createFileRoute('/_authenticated/admin/logs/gateway')({
  component: AdminGatewayLogs,
})
