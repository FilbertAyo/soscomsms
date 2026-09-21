import { createFileRoute } from '@tanstack/react-router'
import { PrivacyPage } from '@/features/legal/privacy'
import { peekSession } from '@/lib/session'

export const Route = createFileRoute('/privacy')({
  beforeLoad: async ({ context }) => {
    await peekSession(context.queryClient)
  },
  component: PrivacyPage,
})
