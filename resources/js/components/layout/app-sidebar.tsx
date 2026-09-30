import { useEffect } from 'react'
import { useLocation, useRouter, type LinkProps } from '@tanstack/react-router'
import { useAuthStore } from '@/stores/auth-store'
import { needsProfileCompletion } from '@/types/auth'
import { useLayout } from '@/context/layout-provider'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { AppTitle } from './app-title'
import { sidebarForPath } from './data/sidebar-data'
import { NavGroup } from './nav-group'
import { NavUser } from './nav-user'
import { type NavGroup as NavGroupData } from './types'

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
  const router = useRouter()
  const pathname = useLocation({ select: (location) => location.pathname })
  const user = useAuthStore((state) => state.user)
  const navKey = user
    ? `${user.role}:${pathname.startsWith('/admin') ? 'admin' : 'customer'}:${needsProfileCompletion(user)}`
    : ''

  useEffect(() => {
    const current = useAuthStore.getState().user
    if (!current) {
      return
    }

    const urls = navUrls(
      sidebarForPath(
        current,
        navKey.includes(':admin:') ? '/admin' : '/dashboard'
      ).navGroups
    )
    if (urls.length === 0) {
      return
    }

    let cancelled = false
    const preload = () => {
      if (cancelled) {
        return
      }
      for (const to of urls) {
        void router.preloadRoute({ to })
      }
    }

    const idle =
      typeof window.requestIdleCallback === 'function'
        ? window.requestIdleCallback(preload, { timeout: 1500 })
        : undefined
    const timer = idle === undefined ? window.setTimeout(preload, 300) : undefined

    return () => {
      cancelled = true
      if (idle !== undefined) {
        window.cancelIdleCallback(idle)
      }
      if (timer !== undefined) {
        window.clearTimeout(timer)
      }
    }
  }, [navKey, router])

  if (!user) {
    return null
  }

  const data = sidebarForPath(user, pathname)

  return (
    <Sidebar collapsible={collapsible} variant={variant}>
      <SidebarHeader>
        <AppTitle />
      </SidebarHeader>
      <SidebarContent>
        {data.navGroups.map((props) => (
          <NavGroup key={props.title} {...props} />
        ))}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={data.user} isAdmin={user.role === 'admin'} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}

function navUrls(groups: NavGroupData[]): LinkProps['to'][] {
  const urls = new Set<LinkProps['to']>()

  for (const group of groups) {
    for (const item of group.items) {
      if (item.url) {
        urls.add(item.url)
      }
      for (const subItem of item.items ?? []) {
        if (subItem.url) {
          urls.add(subItem.url)
        }
      }
    }
  }

  return [...urls]
}
