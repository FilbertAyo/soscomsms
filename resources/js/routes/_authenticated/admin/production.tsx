import { createFileRoute } from '@tanstack/react-router'
import { AdminProduction } from '@/features/admin/production'

export const Route = createFileRoute('/_authenticated/admin/production')({
  component: AdminProduction,
})
