import { Link } from '@tanstack/react-router'
import { formatNumber } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

type SmsBalancePillProps = {
  className?: string
}

export function SmsBalancePill({ className }: SmsBalancePillProps) {
  const balance = useAuthStore((state) => state.user?.customer?.smsBalance)

  if (balance === undefined) {
    return null
  }

  return (
    <Link
      to='/wallet'
      className={cn(
        'flex items-center gap-2 rounded-full border bg-background px-2.5 py-1 text-xs sm:px-3 sm:py-1.5 sm:text-sm',
        className
      )}
    >
      <span className='text-muted-foreground'>SMS Balance</span>
      <span className='font-semibold'>{formatNumber(balance)}</span>
    </Link>
  )
}
