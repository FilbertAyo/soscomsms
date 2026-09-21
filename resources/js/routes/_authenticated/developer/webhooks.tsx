import { createFileRoute } from '@tanstack/react-router'
import { WebhooksPage } from '@/features/developer/webhooks'

export const Route = createFileRoute('/_authenticated/developer/webhooks')({
  component: WebhooksPage,
})
