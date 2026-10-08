import { redirect } from '@tanstack/react-router'

/**
 * Customer-facing developer API (keys, docs, webhooks, landing, help, settings, legal).
 * Set this to true to show those surfaces again. The pages and backend stay in place either way.
 * Admin "API Usage" does not use this flag.
 */
export const developerApiVisible = true

/**
 * Customer webhooks (sidebar link, page, docs section, privacy wording).
 * Keep in sync with SMS_WEBHOOKS_ENABLED on the server.
 */
export const webhooksVisible = false

export function assertDeveloperApiVisible(): void {
  if (!developerApiVisible) {
    throw redirect({ to: '/dashboard' })
  }
}

export function assertWebhooksVisible(): void {
  if (!developerApiVisible || !webhooksVisible) {
    throw redirect({ to: '/developer' })
  }
}
