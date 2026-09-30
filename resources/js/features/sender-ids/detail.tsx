import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import {
  fetchSenderIdRequest,
  senderIdRequestQueryKey,
} from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { RequestTimeline } from '@/components/soscom/request-timeline'
import { PanelSkeleton } from '@/components/soscom/skeletons'
import { StatusBadge } from '@/components/soscom/status-badge'
import { Main } from '@/components/layout/main'
import { CUSTOMER_REQUEST_STEPS } from '@/types/sender-ids'

export function SenderIdRequestDetail({ senderId }: { senderId: string }) {
  const requestQuery = useQuery({
    queryKey: senderIdRequestQueryKey(senderId),
    queryFn: () => fetchSenderIdRequest(senderId),
    enabled: /^\d+$/.test(senderId),
  })
  const item = requestQuery.data

  if (!/^\d+$/.test(senderId) || (requestQuery.isError && !item)) {
    return (
      <>
        <AppHeader />
        <Main>
          <EmptyState
            title='Request not found'
            description='This Sender ID request does not belong to your account.'
            actionLabel='Back to Sender IDs'
            actionTo='/sender-ids'
          />
        </Main>
      </>
    )
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title={`Sender ID: ${item?.name ?? '…'}`}
          description={
            item
              ? `${item.organization} · ${item.purpose}`
              : 'Loading this request.'
          }
          actions={
            <Button variant='outline' asChild>
              <Link to='/sender-ids'>My Sender IDs</Link>
            </Button>
          }
        />
        {item ? (
          <div className='max-w-xl rounded-xl border bg-card p-5'>
            <StatusBadge status={item.status} />
            {item.status === 'rejected' && item.rejectionReason ? (
              <p className='mt-4 text-sm text-destructive'>
                {item.rejectionReason}
              </p>
            ) : null}
            {item.submittedToProviderAt ? (
              <p className='mt-3 text-sm text-muted-foreground'>
                Soscom is reviewing this name. We will update this page when it
                is approved or declined.
              </p>
            ) : null}
            <div className='mt-6'>
              <RequestTimeline
                steps={[...CUSTOMER_REQUEST_STEPS]}
                currentIndex={item.timelineIndex}
              />
            </div>
            {item.status === 'approved' ? (
              <Button className='mt-6' asChild>
                <Link to='/send-sms'>Use Sender ID</Link>
              </Button>
            ) : null}
          </div>
        ) : (
          <PanelSkeleton lines={4} className='max-w-xl' />
        )}
      </Main>
    </>
  )
}
