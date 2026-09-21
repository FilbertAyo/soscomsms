import { Link } from '@tanstack/react-router'
import { BrandLogo } from '@/assets/brand-logo'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'

export function LegalLayout({
  title,
  children,
}: {
  title: string
  children: React.ReactNode
}) {
  const user = useAuthStore((state) => state.user)

  return (
    <div className='min-h-svh bg-background text-foreground'>
      <header className='sticky top-0 z-40 border-b bg-background/80 backdrop-blur'>
        <div className='mx-auto flex h-16 max-w-3xl items-center justify-between px-4'>
          <Link to='/' className='flex items-center'>
            <BrandLogo showWordmark />
          </Link>
          <div className='flex items-center gap-2'>
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
      <main className='mx-auto max-w-3xl px-4 py-12'>
        <h1 className='text-3xl font-semibold tracking-tight'>{title}</h1>
        <p className='mt-2 text-sm text-muted-foreground'>
          SOSCOM Technologies Company Limited · sms.soscom.co.tz · 20 September
          2026
        </p>
        <div className='mt-10 space-y-8 text-sm leading-6'>{children}</div>
      </main>
      <footer className='border-t'>
        <div className='mx-auto flex max-w-3xl flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:justify-between'>
          <BrandLogo showWordmark imgClassName='size-8' />
          <div className='flex flex-wrap gap-4 text-sm text-muted-foreground'>
            <Link to='/terms' className='hover:text-foreground'>
              Terms
            </Link>
            <Link to='/privacy' className='hover:text-foreground'>
              Privacy
            </Link>
            <span>© {new Date().getFullYear()} SOSCOM Technologies</span>
          </div>
        </div>
      </footer>
    </div>
  )
}

export function LegalSection({
  title,
  children,
}: {
  title: string
  children: React.ReactNode
}) {
  return (
    <section>
      <h2 className='mb-2 text-base font-semibold'>{title}</h2>
      <div className='space-y-3 text-muted-foreground'>{children}</div>
    </section>
  )
}
