import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { MessageSquarePlus } from 'lucide-react'
import { useState } from 'react'
import { campaignsQueryKey, fetchCampaigns } from '@/lib/campaigns'
import { formatDateTime, formatNumber, formatTzMobile } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { ListPagination } from '@/components/soscom/list-pagination'
import { PageHeader } from '@/components/soscom/page-header'
import { StatusBadge } from '@/components/soscom/status-badge'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { type Campaign } from '@/types/campaigns'

export function Campaigns() {
  const [tab, setTab] = useState('all')
  const [page, setPage] = useState(1)
  const status = tab === 'all' ? undefined : tab
  const campaignsQuery = useQuery({
    queryKey: [...campaignsQueryKey, status ?? 'all', page],
    queryFn: () => fetchCampaigns({ status, page }),
    refetchInterval: (query) => {
      const items = query.state.data?.campaigns ?? []
      const live = items.some((item) =>
        ['queued', 'scheduled', 'sending'].includes(item.status)
      )
      return live ? 5000 : false
    },
  })
  const items = campaignsQuery.data?.campaigns ?? []
  const meta = campaignsQuery.data?.meta

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Campaigns'
          description='Messages you have sent or scheduled.'
          actions={
            <Button asChild>
              <Link to='/send-sms'>Send SMS</Link>
            </Button>
          }
        />
        {campaignsQuery.isError ? (
          <p className='text-sm text-destructive'>Could not load campaigns.</p>
        ) : (
          <Tabs
            value={tab}
            onValueChange={(value) => {
              setTab(value)
              setPage(1)
            }}
          >
            <TabsList className='mb-4'>
              <TabsTrigger value='all'>All</TabsTrigger>
              <TabsTrigger value='queued'>In progress</TabsTrigger>
              <TabsTrigger value='scheduled'>Scheduled</TabsTrigger>
              <TabsTrigger value='completed'>Completed</TabsTrigger>
            </TabsList>
            <TabsContent value='all'>
              <CampaignTable items={items} />
            </TabsContent>
            <TabsContent value='queued'>
              <CampaignTable items={items} />
            </TabsContent>
            <TabsContent value='scheduled'>
              <CampaignTable items={items} />
            </TabsContent>
            <TabsContent value='completed'>
              <CampaignTable items={items} />
            </TabsContent>
          </Tabs>
        )}
        {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
      </Main>
    </>
  )
}

function CampaignTable({ items }: { items: Campaign[] }) {
  if (items.length === 0) {
    return (
      <EmptyState
        icon={MessageSquarePlus}
        title='No campaigns yet'
        description='Your SMS campaigns will appear here after you send your first message.'
        actionLabel='Send SMS'
        actionTo='/send-sms'
      />
    )
  }

  return (
    <TableWrap>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Campaign</TableHead>
            <TableHead>Phone</TableHead>
            <TableHead>Sender ID</TableHead>
            <TableHead>Recipients</TableHead>
            <TableHead>SMS</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Date</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.map((campaign) => (
            <TableRow key={campaign.id}>
              <TableCell>
                <Link
                  to='/campaigns/$campaignId'
                  params={{ campaignId: String(campaign.id) }}
                  className='font-medium hover:underline'
                >
                  {campaign.name}
                </Link>
              </TableCell>
              <TableCell>
                {campaign.recipientPhone
                  ? formatTzMobile(campaign.recipientPhone)
                  : '—'}
                {campaign.recipients > 1 ? (
                  <span className='text-muted-foreground'>
                    {' '}
                    +{formatNumber(campaign.recipients - 1)}
                  </span>
                ) : null}
              </TableCell>
              <TableCell>{campaign.senderId}</TableCell>
              <TableCell>{formatNumber(campaign.recipients)}</TableCell>
              <TableCell>{formatNumber(campaign.totalSms)}</TableCell>
              <TableCell>
                <StatusBadge status={campaign.status} />
              </TableCell>
              <TableCell>
                {formatDateTime(
                  campaign.scheduledAt ?? campaign.queuedAt ?? campaign.createdAt ?? ''
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableWrap>
  )
}
