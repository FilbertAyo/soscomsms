import { createFileRoute, redirect } from '@tanstack/react-router'
import { AuthenticatedLayout } from '@/components/layout/authenticated-layout'
import { PageLoader } from '@/components/soscom/page-loader'
import { fetchMe, isUnauthorized, meQueryKey } from '@/lib/auth'
import { logClientError } from '@/lib/log-client-error'
import { useAuthStore } from '@/stores/auth-store'
import { isProfileLockedPath, needsProfileCompletion } from '@/types/auth'

export const Route = createFileRoute('/_authenticated')({
  beforeLoad: async ({ context, location }) => {
    let user

    try {
      user = await context.queryClient.ensureQueryData({
        queryKey: meQueryKey,
        queryFn: fetchMe,
        staleTime: 60 * 1000,
      })
    } catch (error) {
      useAuthStore.getState().reset()
      if (isUnauthorized(error)) {
        throw redirect({
          to: '/sign-in',
          search: { redirect: location.href },
        })
      }
      logClientError(error, { source: 'authenticated-session' })
      throw new Error("We couldn't load your session. Please try again.")
    }

    useAuthStore.getState().setUser(user)

    if (
      needsProfileCompletion(user) &&
      isProfileLockedPath(location.pathname)
    ) {
      throw redirect({ to: '/settings/business' })
    }

    return { user }
  },
  pendingComponent: PageLoader,
  component: AuthenticatedLayout,
})
