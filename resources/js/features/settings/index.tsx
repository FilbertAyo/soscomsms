import { Outlet } from '@tanstack/react-router'
import {
  Bell,
  Building2,
  Lock,
  Palette,
  UserCog,
} from 'lucide-react'
import { Separator } from '@/components/ui/separator'
import { AppHeader } from '@/components/soscom/app-header'
import { Main } from '@/components/layout/main'
import { SidebarNav } from './components/sidebar-nav'

const sidebarNavItems = [
  {
    title: 'Appearance',
    href: '/settings/appearance',
    icon: <Palette size={18} />,
  },
  {
    title: 'Account',
    href: '/settings',
    icon: <UserCog size={18} />,
  },
  {
    title: 'Business Information',
    href: '/settings/business',
    icon: <Building2 size={18} />,
  },
  {
    title: 'Password',
    href: '/settings/password',
    icon: <Lock size={18} />,
  },
  {
    title: 'Notifications',
    href: '/settings/notifications',
    icon: <Bell size={18} />,
  },
]

export function Settings() {
  return (
    <>
      <AppHeader />
      <Main fixed>
        <div className='space-y-0.5'>
          <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>
            Settings
          </h1>
          <p className='text-muted-foreground'>
            Manage your profile, business details, and account preferences.
          </p>
        </div>
        <Separator className='my-4 lg:my-6' />
        <div className='flex flex-1 flex-col space-y-2 overflow-hidden md:space-y-2 lg:flex-row lg:space-y-0 lg:space-x-12'>
          <aside className='top-0 lg:sticky lg:w-1/5'>
            <SidebarNav items={sidebarNavItems} />
          </aside>
          <div className='flex w-full overflow-y-hidden p-1'>
            <Outlet />
          </div>
        </div>
      </Main>
    </>
  )
}
