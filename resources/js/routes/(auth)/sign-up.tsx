import { createFileRoute, redirect } from '@tanstack/react-router'
import { SignUp } from '@/features/auth/sign-up'
import { PageLoader } from '@/components/soscom/page-loader'
import { peekSession } from '@/lib/session'

export const Route = createFileRoute('/(auth)/sign-up')({
  pendingComponent: PageLoader,
  beforeLoad: async ({ context }) => {
    const user = await peekSession(context.queryClient)
    if (user) {
      throw redirect({
        to: '/dashboard',
        replace: true,
      })
    }
  },
  component: SignUp,
})
