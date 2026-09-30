import { createFileRoute } from '@tanstack/react-router'
import { AdminSenderIdCatalog } from '@/features/admin/sender-id-catalog'

export const Route = createFileRoute('/_authenticated/admin/sender-id-catalog')({
  component: AdminSenderIdCatalog,
})
