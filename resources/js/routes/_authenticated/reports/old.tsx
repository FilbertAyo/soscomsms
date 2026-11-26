import { createFileRoute } from '@tanstack/react-router'
import { OldDeliveryReportsPage } from '@/features/reports/old'

export const Route = createFileRoute('/_authenticated/reports/old')({
  component: OldDeliveryReportsPage,
})
