import { createFileRoute } from '@tanstack/react-router'
import { LandingPage } from '@/features/landing'
import { peekSession } from '@/lib/session'

export const Route = createFileRoute('/')({
  beforeLoad: async ({ context }) => {
    const user = await peekSession(context.queryClient)
    return { user }
  },
  component: LandingPage,
})
