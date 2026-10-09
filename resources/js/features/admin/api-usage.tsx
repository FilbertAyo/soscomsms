import { useEffect, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import {
  adminApiUsageQueryKey,
  fetchAdminApiKeys,
  fetchAdminApiRequests,
  fetchAdminWebhookDeliveries,
} from '@/lib/api-keys'
import { webhookEventLabel } from '@/lib/webhooks'
import { formatDateTime } from '@/lib/format'
import { ListPagination } from '@/components/soscom/list-pagination'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AdminPage } from './page'

type UsageTab = 'requests' | 'keys' | 'webhooks'

export function AdminApiUsage() {
  const [tab, setTab] = useState<UsageTab>('requests')

  return (
    <AdminPage
      title='API Usage'
      description='Live customer API keys, /api/v1 requests, and webhook delivery attempts.'
    >
      <Tabs value={tab} onValueChange={(value) => setTab(value as UsageTab)}>
        <TabsList>
          <TabsTrigger value='requests'>Recent requests</TabsTrigger>
          <TabsTrigger value='keys'>API keys</TabsTrigger>
          <TabsTrigger value='webhooks'>Webhook deliveries</TabsTrigger>
        </TabsList>
        <TabsContent value='requests' className='mt-4'>
          <RequestsTab />
        </TabsContent>
        <TabsContent value='keys' className='mt-4'>
          <KeysTab />
        </TabsContent>
        <TabsContent value='webhooks' className='mt-4'>
          <WebhooksTab />
        </TabsContent>
      </Tabs>
    </AdminPage>
  )
}

function useSearchPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [term, setTerm] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setTerm(search.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [search])

  return { page, setPage, search, setSearch, term }
}

function SearchBox({
  value,
  onChange,
  placeholder,
  label,
}: {
  value: string
  onChange: (value: string) => void
  placeholder: string
  label: string
}) {
  return (
    <div className='relative mb-3 max-w-sm'>
      <Search className='pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground' />
      <Input
        type='search'
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className='h-8 ps-8 text-xs'
        aria-label={label}
      />
    </div>
  )
}

function EmptyRow({
  colSpan,
  loading,
  term,
  empty,
}: {
  colSpan: number
  loading: boolean
  term: string
  empty: string
}) {
  return (
    <TableRow>
      <TableCell colSpan={colSpan} className='text-muted-foreground'>
        {loading ? 'Loading…' : term ? `Nothing matches “${term}”.` : empty}
      </TableCell>
    </TableRow>
  )
}

function RequestsTab() {
  const { page, setPage, search, setSearch, term } = useSearchPage()
  const query = useQuery({
    queryKey: [...adminApiUsageQueryKey, 'requests', page, term],
    queryFn: () => fetchAdminApiRequests(page, term),
    placeholderData: keepPreviousData,
  })
  const logs = query.data?.logs ?? []

  return (
    <>
      <SearchBox
        value={search}
        onChange={setSearch}
        placeholder='Search customer, path, status, request ID, error…'
        label='Search API requests'
      />
      {query.isError ? (
        <p className='text-sm text-destructive'>Could not load API requests.</p>
      ) : (
        <>
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>When</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Request</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Campaign</TableHead>
                  <TableHead>Error</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {logs.length === 0 ? (
                  <EmptyRow
                    colSpan={6}
                    loading={query.isLoading}
                    term={term}
                    empty='No API requests yet.'
                  />
                ) : (
                  logs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell>
                        {log.createdAt ? formatDateTime(log.createdAt) : '—'}
                      </TableCell>
                      <TableCell>
                        <p className='text-sm'>{log.customerEmail ?? '—'}</p>
                        <p className='font-mono text-xs text-muted-foreground'>
                          {log.keyPrefix ?? ''}
                        </p>
                      </TableCell>
                      <TableCell className='font-mono text-xs'>
                        {log.method} {log.path}
                      </TableCell>
                      <TableCell>{log.statusCode}</TableCell>
                      <TableCell>{log.campaignId ?? '—'}</TableCell>
                      <TableCell className='max-w-xs truncate text-sm text-muted-foreground'>
                        {log.error ?? '—'}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableWrap>
          {query.data ? (
            <ListPagination meta={query.data.meta} onPageChange={setPage} />
          ) : null}
        </>
      )}
    </>
  )
}

function KeysTab() {
  const { page, setPage, search, setSearch, term } = useSearchPage()
  const query = useQuery({
    queryKey: [...adminApiUsageQueryKey, 'keys', page, term],
    queryFn: () => fetchAdminApiKeys(page, term),
    placeholderData: keepPreviousData,
  })
  const keys = query.data?.keys ?? []

  return (
    <>
      <SearchBox
        value={search}
        onChange={setSearch}
        placeholder='Search key name, prefix, or customer…'
        label='Search API keys'
      />
      {query.isError ? (
        <p className='text-sm text-destructive'>Could not load API keys.</p>
      ) : (
        <>
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Key</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Last used</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {keys.length === 0 ? (
                  <EmptyRow
                    colSpan={6}
                    loading={query.isLoading}
                    term={term}
                    empty='No API keys yet.'
                  />
                ) : (
                  keys.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.name}</TableCell>
                      <TableCell>{item.customerEmail ?? '—'}</TableCell>
                      <TableCell className='font-mono text-sm'>
                        {item.masked}
                      </TableCell>
                      <TableCell>
                        {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                      </TableCell>
                      <TableCell>
                        {item.lastUsedAt
                          ? formatDateTime(item.lastUsedAt)
                          : 'Never'}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={item.status} />
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableWrap>
          {query.data ? (
            <ListPagination meta={query.data.meta} onPageChange={setPage} />
          ) : null}
        </>
      )}
    </>
  )
}

function WebhooksTab() {
  const { page, setPage, search, setSearch, term } = useSearchPage()
  const query = useQuery({
    queryKey: [...adminApiUsageQueryKey, 'webhooks', page, term],
    queryFn: () => fetchAdminWebhookDeliveries(page, term),
    placeholderData: keepPreviousData,
  })
  const deliveries = query.data?.webhookDeliveries ?? []

  return (
    <>
      <SearchBox
        value={search}
        onChange={setSearch}
        placeholder='Search customer, event, status, URL, error…'
        label='Search webhook deliveries'
      />
      {query.isError ? (
        <p className='text-sm text-destructive'>
          Could not load webhook deliveries.
        </p>
      ) : (
        <>
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>When</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Event</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Attempt</TableHead>
                  <TableHead>Next retry</TableHead>
                  <TableHead>Error</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {deliveries.length === 0 ? (
                  <EmptyRow
                    colSpan={7}
                    loading={query.isLoading}
                    term={term}
                    empty='No customer webhook posts yet.'
                  />
                ) : (
                  deliveries.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        {row.createdAt ? formatDateTime(row.createdAt) : '—'}
                      </TableCell>
                      <TableCell>{row.customerEmail ?? '—'}</TableCell>
                      <TableCell>{webhookEventLabel(row.event)}</TableCell>
                      <TableCell>
                        <StatusBadge status={row.status} />
                      </TableCell>
                      <TableCell>{row.attempt}</TableCell>
                      <TableCell>
                        {row.nextRetryAt
                          ? formatDateTime(row.nextRetryAt)
                          : '—'}
                      </TableCell>
                      <TableCell className='max-w-xs truncate text-sm text-muted-foreground'>
                        {row.error ?? '—'}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableWrap>
          {query.data ? (
            <ListPagination meta={query.data.meta} onPageChange={setPage} />
          ) : null}
        </>
      )}
    </>
  )
}
