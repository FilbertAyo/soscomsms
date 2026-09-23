import { createFileRoute } from '@tanstack/react-router'
import { WebhooksPage } from '@/features/developer/webhooks'
import { assertDeveloperApiVisible } from '@/lib/features'

export const Route = createFileRoute('/_authenticated/developer/webhooks')({
  beforeLoad: () => {
    assertDeveloperApiVisible()
  },
  component: WebhooksPage,
})
