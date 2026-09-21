import { createFileRoute } from '@tanstack/react-router'
import { AdminSenderIds } from '@/features/admin/sender-ids'

export const Route = createFileRoute('/_authenticated/admin/sender-ids')({
  component: AdminSenderIds,
})
