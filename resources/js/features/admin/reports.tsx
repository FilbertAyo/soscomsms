import { useEffect, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import {
  adminDeliveryReportsQueryKey,
  fetchAdminDeliveryReports,
} from '@/lib/admin'
import { formatDateTime, formatTzMobile } from '@/lib/format'
import { Input } from '@/components/ui/input'
import { ListPagination } from '@/components/soscom/list-pagination'
import { StatusBadge } from '@/components/soscom/status-badge'
import { type AdminDeliveryReport } from '@/types/admin'
import { AdminPage } from './page'

const badgeClass = 'px-1.5 py-0 text-[10px] leading-4'

export function AdminReports() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(search.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [search])

  const reportsQuery = useQuery({
    queryKey: [...adminDeliveryReportsQueryKey, page, debouncedSearch],
    queryFn: () => fetchAdminDeliveryReports(page, debouncedSearch),
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
      description='Platform-wide message status. Message ID is the provider ID to match in the Mobishastra account.'
    >
      <div className='relative mb-3 max-w-sm'>
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
      {reportsQuery.isError ? (
        <p className='text-sm text-destructive'>
          Could not load delivery reports.
        </p>
      ) : (
        <>
          {reports.length === 0 ? (
            <p className='text-xs text-muted-foreground'>
              {reportsQuery.isLoading
                ? 'Loading…'
                : debouncedSearch
                  ? `No delivery reports match “${debouncedSearch}”.`
                  : 'No delivery reports yet.'}
            </p>
          ) : (
            <div className='divide-y rounded-lg border bg-card text-xs'>
              {reports.map((item) => (
                <ReportRow key={item.id} item={item} />
              ))}
            </div>
          )}
          {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
        </>
      )}
    </AdminPage>
  )
}

function ReportRow({ item }: { item: AdminDeliveryReport }) {
  return (
    <div className='flex flex-wrap items-baseline gap-x-3 gap-y-1 px-3 py-2'>
      <span className='w-32 shrink-0 text-[11px] text-muted-foreground tabular-nums'>
        {item.sentAt ? formatDateTime(item.sentAt) : '—'}
      </span>
      {item.status ? (
        <StatusBadge status={item.status} className={badgeClass} />
      ) : null}
      <span className='shrink-0 font-medium tabular-nums'>
        {item.phone ? formatTzMobile(item.phone) : '—'}
      </span>
      <span className='shrink-0 text-muted-foreground'>
        {item.senderId ?? '—'}
      </span>
      <span className='min-w-0 flex-1 truncate'>
        {item.customerName ?? '—'}
        {item.customerEmail ? (
          <span className='text-muted-foreground'> · {item.customerEmail}</span>
        ) : null}
      </span>
      <span className='shrink-0 font-mono text-[11px] text-muted-foreground'>
        {item.providerMessageId || '—'}
      </span>
    </div>
  )
}
