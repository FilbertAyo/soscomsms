import { createFileRoute } from '@tanstack/react-router'
import { TermsPage } from '@/features/legal/terms'
import { peekSession } from '@/lib/session'

export const Route = createFileRoute('/terms')({
  beforeLoad: async ({ context }) => {
    await peekSession(context.queryClient)
  },
  component: TermsPage,
})
