import { Link } from '@tanstack/react-router'
import { Header } from '@/components/layout/header'
import { Search } from '@/components/search'
import { SmsBalancePill } from '@/components/soscom/sms-balance-pill'
import { ThemeSwitch } from '@/components/theme-switch'

type AppHeaderProps = {
  children?: React.ReactNode
}

export function AppHeader({ children }: AppHeaderProps) {
  return (
    <Header>
      {children}
      <Search className='me-auto hidden sm:flex' placeholder='Search...' />
      <div className='ms-auto flex items-center gap-2'>
        <SmsBalancePill />
        <ThemeSwitch />
      </div>
    </Header>
  )
}

export function AdminHeader({ children }: AppHeaderProps) {
  return (
    <Header>
      {children}
      <Search className='me-auto hidden sm:flex' placeholder='Search...' />
      <div className='ms-auto flex items-center gap-2'>
        <Link
          to='/dashboard'
          className='hidden text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline sm:inline'
        >
          Customer app
        </Link>
        <ThemeSwitch />
      </div>
    </Header>
  )
}
