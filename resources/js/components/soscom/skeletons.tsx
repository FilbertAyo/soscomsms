import { Skeleton } from '@/components/ui/skeleton'

export function CardSkeletonGrid({ count = 4 }: { count?: number }) {
  return (
    <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
      {Array.from({ length: count }).map((_, index) => (
        <div key={index} className='rounded-xl border bg-card p-5'>
          <Skeleton className='h-4 w-24' />
          <Skeleton className='mt-4 h-8 w-20' />
          <Skeleton className='mt-3 h-3 w-32' />
        </div>
      ))}
    </div>
  )
}

export function DashboardStatsSkeleton() {
  return (
    <div className='grid gap-4 sm:grid-cols-2 lg:col-span-2'>
      {Array.from({ length: 4 }).map((_, index) => (
        <div key={index} className='rounded-xl border bg-card p-5'>
          <Skeleton className='h-4 w-28' />
          <Skeleton className='mt-3 h-8 w-16' />
        </div>
      ))}
    </div>
  )
}

export function TableSkeleton({ rows = 6 }: { rows?: number }) {
  return (
    <div className='space-y-3 rounded-xl border bg-card p-4'>
      <Skeleton className='h-8 w-full' />
      {Array.from({ length: rows }).map((_, index) => (
        <Skeleton key={index} className='h-10 w-full' />
      ))}
    </div>
  )
}
