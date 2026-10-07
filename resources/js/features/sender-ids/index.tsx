import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { BadgeCheck } from 'lucide-react'
import {
  fetchSenderIdRequests,
  fetchUsableSenderIds,
  senderIdRequestsQueryKey,
  usableSenderIdsQueryKey,
} from '@/lib/sender-ids'
import { formatDate } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { CardSkeletonGrid } from '@/components/soscom/skeletons'
import { StatusBadge } from '@/components/soscom/status-badge'
import { Main } from '@/components/layout/main'
import {
  senderIdUseCaseLabel,
  type SenderIdRequestRecord,
} from '@/types/sender-ids'

const gridClassName =
  'grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'

export function SenderIdsPage() {
  const senderIdsQuery = useQuery({
    queryKey: usableSenderIdsQueryKey,
    queryFn: fetchUsableSenderIds,
  })
  const requestsQuery = useQuery({
    queryKey: senderIdRequestsQueryKey,
    queryFn: fetchSenderIdRequests,
  })

  const senderIds = senderIdsQuery.data ?? []
  const openRequests = (requestsQuery.data ?? []).filter(
    (item) => item.status !== 'approved'
  )
  const isError = senderIdsQuery.isError || requestsQuery.isError
  const isPending = senderIdsQuery.isPending || requestsQuery.isPending
  const empty = senderIds.length === 0 && openRequests.length === 0

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='My Sender IDs'
          description='A Sender ID is the name people see when they receive your SMS. Shared SOSCOM names are ready to use; custom names need approval.'
          actions={
            <Button asChild>
              <Link to='/sender-ids/request'>+ Request Sender ID</Link>
            </Button>
          }
        />
        {isError ? (
          <p className='text-sm text-destructive'>
            Could not load Sender IDs. Refresh and try again.
          </p>
        ) : isPending ? (
          <CardSkeletonGrid count={4} className={gridClassName} />
        ) : empty ? (
          <EmptyState
            icon={BadgeCheck}
            title='No Sender IDs yet'
            description='Use a shared SOSCOM Sender ID when it appears here, or request your own business name.'
            actionLabel='Request Sender ID'
            actionTo='/sender-ids/request'
          />
        ) : (
          <div className={gridClassName}>
            {senderIds.map((item) => (
              <div
                key={`sid-${item.id}`}
                className='flex flex-col rounded-xl border bg-card p-4'
              >
                <div className='flex items-start justify-between gap-2'>
                  <h2 className='min-w-0 truncate text-base font-semibold'>
                    {item.name}
                  </h2>
                  <StatusBadge status={item.status} />
                </div>
                <p className='mt-1 line-clamp-2 text-sm text-muted-foreground'>
                  {item.purpose ?? 'Ready to send'}
                </p>
                {item.source === 'generic' ? (
                  <p className='mt-1 text-xs text-muted-foreground'>
                    Shared SOSCOM Sender ID
                  </p>
                ) : null}
                <p className='mt-3 text-xs text-muted-foreground'>
                  {item.createdAt
                    ? `Added ${formatDate(item.createdAt)}`
                    : item.organization}
                </p>
                <div className='mt-auto pt-3'>
                  <Button size='sm' className='w-full' asChild>
                    <Link to='/send-sms'>Use Sender ID</Link>
                  </Button>
                </div>
              </div>
            ))}
            {openRequests.map((item) => (
              <RequestCard key={`req-${item.id}`} item={item} />
            ))}
          </div>
        )}
      </Main>
    </>
  )
}

function RequestCard({ item }: { item: SenderIdRequestRecord }) {
  return (
    <div className='flex flex-col rounded-xl border bg-card p-4'>
      <div className='flex items-start justify-between gap-2'>
        <h2 className='min-w-0 truncate text-base font-semibold'>
          {item.name}
        </h2>
        <StatusBadge status={item.status} />
      </div>
      <p className='mt-1 line-clamp-2 text-sm text-muted-foreground'>
        {senderIdUseCaseLabel(item.purpose)}
      </p>
      <p className='mt-3 text-xs text-muted-foreground'>
        Requested {item.createdAt ? formatDate(item.createdAt) : 'recently'}
      </p>
      <div className='mt-auto pt-3'>
        <Button variant='outline' size='sm' className='w-full' asChild>
          <Link
            to='/sender-ids/$senderId'
            params={{ senderId: String(item.id) }}
          >
            {item.status === 'needs_resubmission'
              ? 'Upload new document'
              : 'View Request'}
          </Link>
        </Button>
      </div>
    </div>
  )
}
