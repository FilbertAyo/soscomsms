import { createFileRoute, Outlet, redirect } from '@tanstack/react-router'
import { PageLoader } from '@/components/soscom/page-loader'
import { peekSession, safeInternalPath } from '@/lib/session'

export const Route = createFileRoute('/(auth)')({
  beforeLoad: async ({ context, location }) => {
    const user = await peekSession(context.queryClient)
    if (!user) {
      return
    }

    const search = location.search as { redirect?: unknown }
    const redirectTo =
      typeof search.redirect === 'string' ? search.redirect : undefined

    throw redirect({
      to: safeInternalPath(redirectTo),
      replace: true,
    })
  },
  pendingComponent: PageLoader,
  component: AuthGateLayout,
})

function AuthGateLayout() {
  return <Outlet />
}
