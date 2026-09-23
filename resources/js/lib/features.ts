import { redirect } from '@tanstack/react-router'

/**
 * Customer-facing developer API (keys, docs, webhooks, landing, help, settings, legal).
 * Set this to true to show those surfaces again. The pages and backend stay in place either way.
 * Admin "API Usage" does not use this flag.
 */
export const developerApiVisible = false

export function assertDeveloperApiVisible(): void {
  if (!developerApiVisible) {
    throw redirect({ to: '/dashboard' })
  }
}
