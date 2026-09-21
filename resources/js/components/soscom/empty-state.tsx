import { Link } from '@tanstack/react-router'
import { type LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type EmptyStateProps = {
  icon?: LucideIcon
  title: string
  description: string
  actionLabel?: string
  actionTo?: string
  onAction?: () => void
  className?: string
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  actionLabel,
  actionTo,
  onAction,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-14 text-center',
        className
      )}
    >
      {Icon ? (
        <div className='mb-4 flex size-12 items-center justify-center rounded-full bg-muted'>
          <Icon className='size-5 text-muted-foreground' />
        </div>
      ) : null}
      <h2 className='text-lg font-semibold'>{title}</h2>
      <p className='mt-1 max-w-md text-sm text-muted-foreground'>
        {description}
      </p>
      {actionLabel && actionTo ? (
        <Button asChild className='mt-5'>
          <Link to={actionTo as '/'}>{actionLabel}</Link>
        </Button>
      ) : null}
      {actionLabel && onAction ? (
        <Button className='mt-5' onClick={onAction}>
          {actionLabel}
        </Button>
      ) : null}
    </div>
  )
}
