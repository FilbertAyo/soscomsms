import { Link } from '@tanstack/react-router'
import { BrandLogo } from '@/assets/brand-logo'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'

export function PublicSiteFrame({ children }: { children: React.ReactNode }) {
  const user = useAuthStore((state) => state.user)

  return (
    <div className='min-h-svh bg-background text-foreground'>
      <header className='sticky top-0 z-40 border-b bg-background/80 backdrop-blur'>
        <div className='mx-auto flex h-16 max-w-6xl items-center justify-between px-4'>
          <Link to='/' className='flex items-center'>
            <BrandLogo showWordmark />
          </Link>
          <div className='flex items-center gap-2'>
            <ThemeSwitch />
            {user ? (
              <Button asChild>
                <Link to='/dashboard'>Dashboard</Link>
              </Button>
            ) : (
              <>
                <Button variant='ghost' asChild>
                  <Link to='/sign-in'>Login</Link>
                </Button>
                <Button asChild>
                  <Link to='/sign-up'>Register</Link>
                </Button>
              </>
            )}
          </div>
        </div>
      </header>
      {children}
      <footer className='border-t'>
        <div className='mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-center sm:justify-between'>
          <div className='flex items-center'>
            <BrandLogo showWordmark imgClassName='size-8' />
          </div>
          <div className='flex flex-wrap items-center gap-4 text-sm text-muted-foreground'>
            <Link to='/terms' className='hover:text-foreground'>
              Terms
            </Link>
            <Link to='/privacy' className='hover:text-foreground'>
              Privacy
            </Link>
            <p>© {new Date().getFullYear()} SOSCOM Technologies</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
