import { type QueryClient } from '@tanstack/react-query'
import { fetchMe, isUnauthorized, meQueryKey } from '@/lib/auth'
import { useAuthStore } from '@/stores/auth-store'
import { type AuthUser } from '@/types/auth'

const GUEST_ONLY_PREFIXES = [
  '/sign-in',
  '/sign-up',
  '/forgot-password',
  '/reset-password',
  '/otp',
] as const

export function isGuestAuthPath(pathname: string): boolean {
  return GUEST_ONLY_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
  )
}

export function isPublicAuthPath(pathname: string): boolean {
  return (
    pathname === '/' ||
    pathname === '/terms' ||
    pathname === '/privacy' ||
    isGuestAuthPath(pathname)
  )
}

export function safeInternalPath(value?: string | null): string {
  if (!value) {
    return '/dashboard'
  }

  let pathname = value
  let search = ''

  try {
    const url = value.startsWith('http://') || value.startsWith('https://')
      ? new URL(value)
      : new URL(value, window.location.origin)

    if (url.origin !== window.location.origin) {
      return '/dashboard'
    }

    pathname = url.pathname
    search = url.search
  } catch {
    if (!value.startsWith('/') || value.startsWith('//')) {
      return '/dashboard'
    }
  }

  if (pathname === '/' || isGuestAuthPath(pathname)) {
    return '/dashboard'
  }

  return `${pathname}${search}`
}

export async function peekSession(
  queryClient: QueryClient
): Promise<AuthUser | null> {
  try {
    const user = await queryClient.fetchQuery({
      queryKey: meQueryKey,
      queryFn: fetchMe,
      retry: false,
      staleTime: 30 * 1000,
    })
    useAuthStore.getState().setUser(user)
    return user
  } catch (error) {
    if (isUnauthorized(error)) {
      queryClient.removeQueries({ queryKey: meQueryKey })
      useAuthStore.getState().reset()
      return null
    }

    throw error
  }
}
