import { Main } from '@/components/layout/main'
import { TableSkeleton } from '@/components/soscom/skeletons'
import { Skeleton } from '@/components/ui/skeleton'

export function RoutePending() {
  return (
    <Main>
      <div className='space-y-4'>
        <Skeleton className='h-8 w-48' />
        <Skeleton className='h-4 w-72 max-w-full' />
        <TableSkeleton rows={5} />
      </div>
    </Main>
  )
}

export function PageLoader() {
  return (
    <div className='flex min-h-svh items-center justify-center bg-background'>
      <div className='flex flex-col items-center gap-3'>
        <img
          src='/images/soscom-sms-icon.png'
          alt='Soscom SMS'
          className='size-10 object-contain brand-logo-img'
        />
        <p className='text-sm text-muted-foreground'>Loading…</p>
      </div>
    </div>
  )
}
