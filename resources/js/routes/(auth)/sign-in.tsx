import { z } from 'zod'
import { createFileRoute, redirect } from '@tanstack/react-router'
import { SignIn } from '@/features/auth/sign-in'
import { PageLoader } from '@/components/soscom/page-loader'
import { peekSession, safeInternalPath } from '@/lib/session'

const searchSchema = z.object({
  redirect: z.string().optional(),
})

export const Route = createFileRoute('/(auth)/sign-in')({
  validateSearch: searchSchema,
  pendingComponent: PageLoader,
  beforeLoad: async ({ context, search }) => {
    const user = await peekSession(context.queryClient)
    if (user) {
      throw redirect({
        to: safeInternalPath(search.redirect),
        replace: true,
      })
    }
  },
  component: SignIn,
})
