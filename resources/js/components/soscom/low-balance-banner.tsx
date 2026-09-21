import { Link } from '@tanstack/react-router'
import { TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { LOW_BALANCE_THRESHOLD } from '@/data/sms'
import { formatNumber } from '@/lib/format'

type LowBalanceBannerProps = {
  balance: number
  onDismiss?: () => void
}

export function LowBalanceBanner({
  balance,
  onDismiss,
}: LowBalanceBannerProps) {
  if (balance >= LOW_BALANCE_THRESHOLD) return null

  return (
    <div className='flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/30'>
      <div className='flex items-start gap-3'>
        <TriangleAlert className='mt-0.5 size-4 text-amber-700 dark:text-amber-400' />
        <div>
          <p className='font-medium text-amber-950 dark:text-amber-100'>
            Your SMS balance is running low
          </p>
          <p className='text-sm text-amber-800 dark:text-amber-300'>
            You have {formatNumber(balance)} SMS remaining.
          </p>
        </div>
      </div>
      <div className='flex items-center gap-2'>
        {onDismiss ? (
          <Button variant='ghost' size='sm' onClick={onDismiss}>
            Dismiss
          </Button>
        ) : null}
        <Button size='sm' asChild>
          <Link to='/wallet/buy'>Buy SMS</Link>
        </Button>
      </div>
    </div>
  )
}
