import { createFileRoute } from '@tanstack/react-router'
import { WebhooksPage } from '@/features/developer/webhooks'
import { assertWebhooksVisible } from '@/lib/features'

export const Route = createFileRoute('/_authenticated/developer/webhooks')({
  beforeLoad: () => {
    assertWebhooksVisible()
  },
  component: WebhooksPage,
})
