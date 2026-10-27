import { StrictMode } from 'react'
import ReactDOM from 'react-dom/client'
import {
  MutationCache,
  QueryCache,
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query'
import { RouterProvider, createRouter } from '@tanstack/react-router'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { isUnauthorized } from '@/lib/api-error'
import { handleServerError } from '@/lib/handle-server-error'
import { logClientError } from '@/lib/log-client-error'
import { meQueryKey } from '@/lib/auth'
import { queryRetry, queryRetryDelay } from '@/lib/retry'
import { DefaultPending } from '@/components/soscom/page-loader'
import { isPublicAuthPath } from '@/lib/session'
import { ErrorBoundary } from '@/components/error-boundary'
import { DirectionProvider } from './context/direction-provider'
import { FontProvider } from './context/font-provider'
import { ThemeProvider } from './context/theme-provider'
import { routeTree } from './routeTree.gen'
import './styles/index.css'

function expireSession(): void {
  useAuthStore.getState().reset()
  queryClient.removeQueries({ queryKey: meQueryKey })
  const path = router.history.location.pathname
  if (isPublicAuthPath(path)) {
    return
  }

  toast.error('Session expired. Please sign in again.')
  void router.navigate({
    to: '/sign-in',
    search: { redirect: router.history.location.href },
  })
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: queryRetry,
      retryDelay: queryRetryDelay,
      refetchOnWindowFocus: import.meta.env.PROD,
      staleTime: 10 * 1000,
    },
    mutations: {
      retry: false,
    },
  },
  queryCache: new QueryCache({
    onError: (error) => {
      if (isUnauthorized(error)) {
        expireSession()
      }
    },
  }),
  mutationCache: new MutationCache({
    onError: (error, _variables, _context, mutation) => {
      if (isUnauthorized(error)) {
        expireSession()
      }

      if (mutation.options.onError) {
        return
      }

      handleServerError(error)
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
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <ThemeProvider defaultTheme='light'>
            <FontProvider>
              <DirectionProvider>
                <RouterProvider router={router} />
              </DirectionProvider>
            </FontProvider>
          </ThemeProvider>
        </QueryClientProvider>
      </ErrorBoundary>
    </StrictMode>
  )
}

window.addEventListener('unhandledrejection', (event) => {
  logClientError(event.reason, { source: 'unhandledrejection' })
})
