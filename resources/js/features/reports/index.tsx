import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileText } from 'lucide-react'
import {
  deliveryReportsQuery,
  exportDeliveryReports,
  fetchCampaigns,
  fetchDeliveryReports,
  campaignsQueryKey,
} from '@/lib/campaigns'
import { fetchUsableSenderIds, usableSenderIdsQueryKey } from '@/lib/sender-ids'
import { formatDateTime } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { ListPagination } from '@/components/soscom/list-pagination'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

export function DeliveryReportsPage() {
  const [campaign, setCampaign] = useState('all')
  const [senderId, setSenderId] = useState('all')
  const [status, setStatus] = useState('all')
  const [recipient, setRecipient] = useState('')
  const [date, setDate] = useState('')
  const [exporting, setExporting] = useState(false)
  const [page, setPage] = useState(1)

  const filters = useMemo(
    () => ({
      campaignId: campaign === 'all' ? '' : campaign,
      senderId: senderId === 'all' ? '' : senderId,
      status: status === 'all' ? '' : status,
      q: recipient.trim(),
      date,
      page,
    }),
    [campaign, date, page, recipient, senderId, status]
  )

  const campaignsQuery = useQuery({
    queryKey: campaignsQueryKey,
    queryFn: () => fetchCampaigns({ perPage: 100 }),
  })
  const senderIdsQuery = useQuery({
    queryKey: usableSenderIdsQueryKey,
    queryFn: fetchUsableSenderIds,
  })
  const reportsQuery = useQuery({
    queryKey: deliveryReportsQuery(filters),
    queryFn: () => fetchDeliveryReports({ ...filters, perPage: 50 }),
    refetchInterval: (query) => {
      const items = query.state.data?.reports ?? []
      const live = items.some((item) =>
        ['queued', 'pending', 'sent', 'unknown'].includes(item.status ?? '')
      )
      return live ? 5000 : false
    },
  })

  const campaigns = campaignsQuery.data?.campaigns ?? []
  const senderIds = senderIdsQuery.data ?? []
  const reports = reportsQuery.data?.reports ?? []
  const meta = reportsQuery.data?.meta

  async function onExport() {
    setExporting(true)
    try {
      await exportDeliveryReports(filters)
      toast.success('Report downloaded.')
    } catch {
      toast.error('Could not export delivery reports.')
    } finally {
      setExporting(false)
    }
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Delivery Reports'
          description='See whether each message was delivered or failed.'
          actions={
            <Button
              variant='outline'
              onClick={() => void onExport()}
              disabled={exporting}
            >
              {exporting ? 'Exporting…' : 'Export Report'}
            </Button>
          }
        />
        <p className='mb-4 rounded-xl border bg-muted/40 px-4 py-3 text-sm text-muted-foreground'>
          We show the delivery report the telecom network provided. Not
          confirmed means no delivery confirmation yet — the SMS may still
          have reached the phone.
        </p>
        <div className='mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5'>
          <Input
            type='date'
            value={date}
            onChange={(event) => setDate(event.target.value)}
          />
          <Select
            value={campaign}
            onValueChange={(value) => {
              setCampaign(value)
              setPage(1)
            }}
          >
            <SelectTrigger>
              <SelectValue placeholder='Campaign' />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All campaigns</SelectItem>
              {campaigns.map((item) => (
                <SelectItem key={item.id} value={String(item.id)}>
                  {item.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={senderId} onValueChange={setSenderId}>
            <SelectTrigger>
              <SelectValue placeholder='Sender ID' />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All Sender IDs</SelectItem>
              {senderIds.map((item) => (
                <SelectItem key={item.id} value={item.name}>
                  {item.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger>
              <SelectValue placeholder='Status' />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All statuses</SelectItem>
              <SelectItem value='delivered'>Delivered</SelectItem>
              <SelectItem value='sent'>Sent</SelectItem>
              <SelectItem value='failed'>Failed</SelectItem>
              <SelectItem value='rejected'>Rejected</SelectItem>
              <SelectItem value='unknown'>Not confirmed</SelectItem>
            </SelectContent>
          </Select>
          <Input
            placeholder='Recipient'
            value={recipient}
            onChange={(event) => setRecipient(event.target.value)}
          />
        </div>
        {reportsQuery.isError ? (
          <p className='text-sm text-destructive'>
            Could not load delivery reports.
          </p>
        ) : reportsQuery.isPending ? (
          <p className='text-sm text-muted-foreground'>Loading reports…</p>
        ) : reports.length === 0 ? (
          <EmptyState
            icon={FileText}
            title='No delivery reports'
            description='Try another filter, or send an SMS to generate reports.'
            actionLabel='Send SMS'
            actionTo='/send-sms'
          />
        ) : (
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Recipient</TableHead>
                  <TableHead>Message</TableHead>
                  <TableHead>Sender ID</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Sent At</TableHead>
                  <TableHead>Delivered At</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {reports.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.phone}</TableCell>
                    <TableCell className='max-w-xs truncate'>
                      {item.message}
                    </TableCell>
                    <TableCell>{item.senderId}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status ?? 'unknown'} />
                    </TableCell>
                    <TableCell>
                      {item.sentAt ? formatDateTime(item.sentAt) : '—'}
                    </TableCell>
                    <TableCell>
                      {item.deliveredAt ? formatDateTime(item.deliveredAt) : '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableWrap>
        )}
        {meta ? (
          <ListPagination
            meta={meta}
            onPageChange={(next) => setPage(next)}
          />
        ) : null}
      </Main>
    </>
  )
}
