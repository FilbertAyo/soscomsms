import { createFileRoute } from '@tanstack/react-router'
import { BuySmsPage } from '@/features/wallet/buy'

export const Route = createFileRoute('/_authenticated/wallet/buy')({
  component: BuySmsPage,
})
