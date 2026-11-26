import { useEffect, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import {
  adminDeliveryReportsQueryKey,
  fetchAdminDeliveryReports,
} from '@/lib/admin'
import { formatDateTime, formatTzMobile } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ListPagination } from '@/components/soscom/list-pagination'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { type AdminDeliveryReport } from '@/types/admin'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { AdminPage } from './page'

const badgeClass = 'px-1.5 py-0 text-[10px] leading-4'
const MESSAGE_PREVIEW_LENGTH = 40

export function AdminReports() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const rangeInvalid = from !== '' && to !== '' && from > to

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(search.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [search])

  const reportsQuery = useQuery({
    queryKey: [...adminDeliveryReportsQueryKey, page, debouncedSearch, from, to],
    queryFn: () =>
      fetchAdminDeliveryReports({
        page,
        q: debouncedSearch,
        from,
        to,
      }),
    enabled: !rangeInvalid,
    placeholderData: keepPreviousData,
    refetchInterval: (query) => {
      const items = query.state.data?.reports ?? []
      const live = items.some((item) =>
        ['queued', 'pending', 'sent', 'unknown'].includes(item.status ?? '')
      )
      return live ? 5000 : false
    },
  })
  const reports = reportsQuery.data?.reports ?? []
  const meta = reportsQuery.data?.meta

  return (
    <AdminPage
      title='Delivery Reports'
      description='Platform-wide message status from the last 30 days. Set a date range to look further back. Message ID is the provider ID to match in the Mobishastra account.'
    >
      <div className='mb-3 flex flex-col gap-2 sm:flex-row sm:items-center'>
        <div className='relative max-w-sm flex-1'>
          <Search className='pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground' />
          <Input
            type='search'
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder='Search phone, message ID, Sender ID, customer…'
            className='h-8 ps-8 text-xs'
            aria-label='Search delivery reports'
          />
        </div>
        <Input
          type='date'
          value={from}
          max={to || undefined}
          onChange={(event) => {
            setFrom(event.target.value)
            setPage(1)
          }}
          className='h-8 w-full text-xs sm:w-40'
          aria-label='From date'
        />
        <Input
          type='date'
          value={to}
          min={from || undefined}
          onChange={(event) => {
            setTo(event.target.value)
            setPage(1)
          }}
          className='h-8 w-full text-xs sm:w-40'
          aria-label='To date'
        />
        {from || to ? (
          <Button
            type='button'
            variant='ghost'
            size='sm'
            onClick={() => {
              setFrom('')
              setTo('')
              setPage(1)
            }}
          >
            Clear dates
          </Button>
        ) : null}
      </div>
      <p className='mb-3 text-xs text-muted-foreground'>
        {rangeInvalid
          ? 'From must be on or before to.'
          : from || to
            ? 'Showing the date range you set. Search stays inside that range.'
            : 'Showing the last 30 days. Set From or To to look further back.'}
      </p>
      {reportsQuery.isError ? (
        <QueryLoadError
          message="We couldn't load delivery reports."
          onRetry={() => void reportsQuery.refetch()}
        />
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Sent at</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Recipient</TableHead>
                <TableHead>Sender ID</TableHead>
                <TableHead>Message</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead className='text-end'>Message ID</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {reports.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className='text-muted-foreground'>
                    {reportsQuery.isLoading
                      ? 'Loading…'
                      : debouncedSearch
                        ? `No delivery reports match “${debouncedSearch}”.`
                        : 'No delivery reports yet.'}
                  </TableCell>
                </TableRow>
              ) : (
                reports.map((item) => <ReportRow key={item.id} item={item} />)
              )}
            </TableBody>
          </Table>
        </TableWrap>
      )}
      {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
    </AdminPage>
  )
}

function ReportRow({ item }: { item: AdminDeliveryReport }) {
  return (
    <TableRow>
      <TableCell className='text-muted-foreground tabular-nums'>
        {item.sentAt ? formatDateTime(item.sentAt) : '—'}
      </TableCell>
      <TableCell>
        {item.status ? (
          <StatusBadge status={item.status} className={badgeClass} />
        ) : (
          '—'
        )}
      </TableCell>
      <TableCell className='font-medium tabular-nums'>
        {item.phone ? formatTzMobile(item.phone) : '—'}
      </TableCell>
      <TableCell>{item.senderId ?? '—'}</TableCell>
      <TableCell
        className='max-w-64 truncate text-muted-foreground'
        title={item.message ?? undefined}
      >
        {previewMessage(item.message)}
      </TableCell>
      <TableCell className='max-w-56 truncate'>
        {item.customerName ?? '—'}
        {item.customerEmail ? (
          <span className='text-muted-foreground'> · {item.customerEmail}</span>
        ) : null}
      </TableCell>
      <TableCell className='text-end font-mono text-muted-foreground'>
        {item.providerMessageId || '—'}
      </TableCell>
    </TableRow>
  )
}

function previewMessage(message: string | null): string {
  if (!message) return '—'
  const flat = message.replace(/\s+/g, ' ').trim()
  return flat.length > MESSAGE_PREVIEW_LENGTH
    ? `${flat.slice(0, MESSAGE_PREVIEW_LENGTH).trimEnd()}…`
    : flat
}
