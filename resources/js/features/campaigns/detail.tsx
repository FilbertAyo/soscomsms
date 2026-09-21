import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useState } from 'react'
import { toast } from 'sonner'
import {
  campaignQueryKey,
  campaignsQueryKey,
  cancelCampaign,
  fetchCampaign,
} from '@/lib/campaigns'
import { creditTransactionsQueryKey, walletQueryKey, withSmsBalance } from '@/lib/credits'
import { formatDateTime, formatNumber } from '@/lib/format'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { StatusBadge } from '@/components/soscom/status-badge'
import { ListPagination } from '@/components/soscom/list-pagination'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

const LIVE_STATUSES = ['queued', 'scheduled', 'sending'] as const

export function CampaignDetail({ campaignId }: { campaignId: string }) {
  const [cancelOpen, setCancelOpen] = useState(false)
  const [recipientPage, setRecipientPage] = useState(1)
  const queryClient = useQueryClient()
  const campaignQuery = useQuery({
    queryKey: [...campaignQueryKey(campaignId), recipientPage],
    queryFn: () => fetchCampaign(campaignId, recipientPage),
    enabled: /^\d+$/.test(campaignId),
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && LIVE_STATUSES.includes(status as (typeof LIVE_STATUSES)[number])
        ? 5000
        : false
    },
  })
  const campaign = campaignQuery.data

  const cancelMutation = useMutation({
    mutationFn: () => cancelCampaign(campaignId),
    onSuccess: async (result) => {
      const current = useAuthStore.getState().user
      if (current) {
        useAuthStore.getState().setUser(withSmsBalance(current, result.smsBalance))
      }
      queryClient.setQueryData(campaignQueryKey(campaignId), result.campaign)
      await queryClient.invalidateQueries({ queryKey: campaignsQueryKey })
      await queryClient.invalidateQueries({ queryKey: walletQueryKey })
      await queryClient.invalidateQueries({ queryKey: creditTransactionsQueryKey })
      setCancelOpen(false)
      toast.success('Scheduled SMS cancelled. The SMS has been returned to your balance.')
    },
    onError: () => {
      toast.error('This campaign can no longer be cancelled.')
    },
  })

  if (!/^\d+$/.test(campaignId) || campaignQuery.isError) {
    return (
      <>
        <AppHeader />
        <Main>
          <EmptyState
            title='Campaign not found'
            description='This campaign does not belong to your account.'
            actionLabel='Back to campaigns'
            actionTo='/campaigns'
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
          title={campaign?.name ?? 'Campaign'}
          description={
            campaign
              ? `From ${campaign.senderId} · ${campaign.segmentsPerRecipient ?? 0} SMS per person`
              : 'Loading campaign…'
          }
          actions={
            <div className='flex flex-wrap gap-2'>
              {campaign?.status === 'scheduled' ? (
                <Button
                  variant='outline'
                  onClick={() => setCancelOpen(true)}
                >
                  Cancel schedule
                </Button>
              ) : null}
              <Button variant='outline' asChild>
                <Link to='/campaigns'>All campaigns</Link>
              </Button>
            </div>
          }
        />
        {campaign ? (
          <>
            <div className='mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
              <Stat label='Recipients' value={formatNumber(campaign.recipients)} />
              <Stat label='Total SMS' value={formatNumber(campaign.totalSms)} />
              <Stat label='Delivered' value={formatNumber(campaign.delivered)} />
              <Stat label='Failed' value={formatNumber(campaign.failed)} />
            </div>
            <div className='mb-6 rounded-xl border bg-card p-5'>
              <div className='flex flex-wrap items-center gap-3'>
                <StatusBadge status={campaign.status} />
                <span className='text-sm text-muted-foreground'>
                  {formatDateTime(
                    campaign.scheduledAt ??
                      campaign.queuedAt ??
                      campaign.createdAt ??
                      ''
                  )}
                </span>
              </div>
              <p className='mt-4 text-sm'>{campaign.message}</p>
              {campaign.status === 'queued' ? (
                <p className='mt-3 text-sm text-muted-foreground'>
                  We are sending this now. Delivery status will update on this
                  page.
                </p>
              ) : null}
              {campaign.status === 'scheduled' ? (
                <p className='mt-3 text-sm text-muted-foreground'>
                  This will be sent at the scheduled time. SMS has already been
                  taken from your balance.
                </p>
              ) : null}
              {campaign.status === 'sending' ? (
                <p className='mt-3 text-sm text-muted-foreground'>
                  We are waiting to hear if each number received the message.
                </p>
              ) : null}
              {campaign.status === 'cancelled' ? (
                <p className='mt-3 text-sm text-muted-foreground'>
                  This was cancelled before it was sent. The SMS was returned to
                  your balance.
                </p>
              ) : null}
            </div>
            <TableWrap>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Recipient</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>SMS</TableHead>
                    <TableHead>Delivered At</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(campaign.recipientRows ?? []).map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.phone}</TableCell>
                      <TableCell>
                        <StatusBadge status={item.status} />
                      </TableCell>
                      <TableCell>{item.segmentCount}</TableCell>
                      <TableCell>
                        {item.deliveredAt ? formatDateTime(item.deliveredAt) : '—'}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableWrap>
            {campaign.recipientMeta ? (
              <ListPagination
                meta={campaign.recipientMeta}
                onPageChange={setRecipientPage}
              />
            ) : null}
          </>
        ) : null}
      </Main>
      <ConfirmDialog
        open={cancelOpen}
        onOpenChange={setCancelOpen}
        title='Cancel this scheduled SMS?'
        desc='This SMS will not be sent. The SMS will go back to your balance.'
        confirmText={cancelMutation.isPending ? 'Cancelling…' : 'Cancel schedule'}
        cancelBtnText='Keep scheduled'
        destructive
        isLoading={cancelMutation.isPending}
        handleConfirm={() => cancelMutation.mutate()}
      />
    </>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className='rounded-xl border bg-card p-5'>
      <p className='text-sm text-muted-foreground'>{label}</p>
      <p className='mt-2 text-2xl font-semibold'>{value}</p>
    </div>
  )
}
