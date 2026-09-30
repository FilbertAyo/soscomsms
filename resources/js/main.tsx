import { StrictMode } from 'react'
import ReactDOM from 'react-dom/client'
import { AxiosError } from 'axios'
import {
  QueryCache,
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query'
import { RouterProvider, createRouter } from '@tanstack/react-router'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { handleServerError } from '@/lib/handle-server-error'
import { meQueryKey } from '@/lib/auth'
import { DefaultPending } from '@/components/soscom/page-loader'
import { isPublicAuthPath } from '@/lib/session'
import { DirectionProvider } from './context/direction-provider'
import { FontProvider } from './context/font-provider'
import { ThemeProvider } from './context/theme-provider'
import { routeTree } from './routeTree.gen'
import './styles/index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failureCount, error) => {
        if (failureCount >= 0 && import.meta.env.DEV) return false
        if (failureCount > 3 && import.meta.env.PROD) return false

        return !(
          error instanceof AxiosError &&
          [401, 403].includes(error.response?.status ?? 0)
        )
      },
      refetchOnWindowFocus: import.meta.env.PROD,
      staleTime: 10 * 1000,
    },
    mutations: {
      onError: (error) => {
        handleServerError(error)

        if (error instanceof AxiosError) {
          if (error.response?.status === 304) {
            toast.error('Nothing changed.')
          }
        }
      },
    },
  },
  queryCache: new QueryCache({
    onError: (error) => {
      if (!(error instanceof AxiosError)) {
        return
      }

      if (error.response?.status === 401) {
        useAuthStore.getState().reset()
        queryClient.removeQueries({ queryKey: meQueryKey })
        const path = router.history.location.pathname
        if (!isPublicAuthPath(path)) {
          toast.error('Session expired. Please sign in again.')
          router.navigate({
            to: '/sign-in',
            search: { redirect: router.history.location.href },
          })
        }
      }

      if ((error.response?.status ?? 0) >= 500) {
        console.error(error)
        toast.error('Something went wrong. Please try again.')
      }
    },
  }),
})

const router = createRouter({
  routeTree,
  context: { queryClient, user: null },
  defaultPreload: 'intent',
  defaultPreloadStaleTime: 30_000,
  defaultPendingMs: 0,
  defaultPendingMinMs: 0,
  defaultPendingComponent: DefaultPending,
})

declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}

const rootElement = document.getElementById('root')!
if (!rootElement.innerHTML) {
  const root = ReactDOM.createRoot(rootElement)
  root.render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider defaultTheme='light'>
          <FontProvider>
            <DirectionProvider>
              <RouterProvider router={router} />
            </DirectionProvider>
          </FontProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </StrictMode>
  )
}
