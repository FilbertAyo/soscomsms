import { createFileRoute } from '@tanstack/react-router'
import { AdminOverview } from '@/features/admin'

export const Route = createFileRoute('/_authenticated/admin/')({
  component: AdminOverview,
})
