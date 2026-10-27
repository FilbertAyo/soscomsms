import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type QueryLoadErrorProps = {
  className?: string
  message?: string
  onRetry?: () => void
}

export function QueryLoadError({
  className,
  message = "We couldn't load this information right now.",
  onRetry,
}: QueryLoadErrorProps) {
  return (
    <div className={cn('space-y-2', className)}>
      <p className='text-sm text-muted-foreground'>{message}</p>
      {onRetry ? (
        <Button type='button' variant='outline' size='sm' onClick={() => void onRetry()}>
          Try again
        </Button>
      ) : null}
    </div>
  )
}
