import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

const tones = {
  success:
    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-400',
  warning:
    'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400',
  danger:
    'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400',
  info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-400',
  muted:
    'border-border bg-muted text-muted-foreground',
} as const

const labels: Record<string, { label: string; tone: keyof typeof tones }> = {
  active: { label: 'Active', tone: 'success' },
  pending: { label: 'Pending', tone: 'warning' },
  rejected: { label: 'Rejected', tone: 'danger' },
  draft: { label: 'Draft', tone: 'muted' },
  queued: { label: 'Queued', tone: 'warning' },
  scheduled: { label: 'Scheduled', tone: 'info' },
  sending: { label: 'Sending', tone: 'warning' },
  completed: { label: 'Completed', tone: 'success' },
  partially_delivered: { label: 'Partially Delivered', tone: 'info' },
  failed: { label: 'Failed', tone: 'danger' },
  sent: { label: 'Sent', tone: 'info' },
  delivered: { label: 'Delivered', tone: 'success' },
  unknown: { label: 'Sent', tone: 'info' },
  successful: { label: 'Successful', tone: 'success' },
  inactive: { label: 'Inactive', tone: 'muted' },
  suspended: { label: 'Paused', tone: 'danger' },
  revoked: { label: 'Revoked', tone: 'muted' },
  under_review: { label: 'Under review', tone: 'warning' },
  submitted: { label: 'Request submitted', tone: 'info' },
  needs_resubmission: { label: 'Resubmit document', tone: 'warning' },
  submitted_to_provider: { label: 'Awaiting approval', tone: 'warning' },
  approved: { label: 'Approved', tone: 'success' },
  cancelled: { label: 'Cancelled', tone: 'muted' },
  blocked: { label: 'Blocked', tone: 'danger' },
  timeout: { label: 'Timeout', tone: 'warning' },
  ok: { label: 'OK', tone: 'success' },
  error: { label: 'Error', tone: 'danger' },
  grant: { label: 'Bonus', tone: 'success' },
  reversal: { label: 'Reversed', tone: 'warning' },
  purchase: { label: 'Purchase', tone: 'info' },
  usage: { label: 'Used', tone: 'muted' },
  refund: { label: 'Refund', tone: 'success' },
  pending_profile: { label: 'Pending profile', tone: 'warning' },
  paid: { label: 'Paid', tone: 'success' },
  expired: { label: 'Expired', tone: 'muted' },
  lipa_namba: { label: 'Lipa namba', tone: 'info' },
}

type StatusBadgeProps = {
  status: string
  className?: string
}

export function statusLabel(status: string): string {
  return labels[status]?.label ?? status.replaceAll('_', ' ')
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const config = labels[status] ?? {
    label: status,
    tone: 'muted' as const,
  }

  return (
    <Badge
      variant='outline'
      className={cn('font-medium', tones[config.tone], className)}
    >
      {config.label}
    </Badge>
  )
}
