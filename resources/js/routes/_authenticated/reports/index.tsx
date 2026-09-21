import { createFileRoute } from '@tanstack/react-router'
import { DeliveryReportsPage } from '@/features/reports'

export const Route = createFileRoute('/_authenticated/reports/')({
  component: DeliveryReportsPage,
})
