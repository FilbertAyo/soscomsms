import { createFileRoute } from '@tanstack/react-router'
import { SenderIdsPage } from '@/features/sender-ids'

export const Route = createFileRoute('/_authenticated/sender-ids/')({
  component: SenderIdsPage,
})
