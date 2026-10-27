import { useEffect } from 'react'
import { logClientError } from '@/lib/log-client-error'
import { cn } from '@/lib/utils'
import { FriendlyError } from './friendly-error'

type GeneralErrorProps = React.HTMLAttributes<HTMLDivElement> & {
  minimal?: boolean
  error?: unknown
}

export function GeneralError({
  className,
  minimal = false,
  error,
}: GeneralErrorProps) {
  useEffect(() => {
    if (error) {
      logClientError(error, { source: 'router-error' })
    }
  }, [error])

  if (minimal) {
    return (
      <p className={cn('text-sm text-muted-foreground', className)}>
        Something went wrong. Please try again.
      </p>
    )
  }

  return (
    <div className={className}>
      <FriendlyError
        title='Something went wrong'
        description='We could not finish that. Try again in a moment. If it keeps happening, contact Soscom.'
        homeTo='/dashboard'
        homeLabel='Back to dashboard'
      />
    </div>
  )
}
