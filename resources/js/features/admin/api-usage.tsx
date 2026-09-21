import { useQuery } from '@tanstack/react-query'
import { adminApiUsageQueryKey, fetchAdminApiUsage } from '@/lib/api-keys'
import { webhookEventLabel } from '@/lib/webhooks'
import { formatDateTime } from '@/lib/format'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AdminPage } from './page'

export function AdminApiUsage() {
  const usageQuery = useQuery({
    queryKey: adminApiUsageQueryKey,
    queryFn: fetchAdminApiUsage,
  })
  const logs = usageQuery.data?.logs ?? []
  const keys = usageQuery.data?.keys ?? []
  const webhookDeliveries = usageQuery.data?.webhookDeliveries ?? []

  return (
    <AdminPage
      title='API Usage'
      description='Live customer API keys, /api/v1 requests, and webhook delivery attempts.'
    >
      {usageQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load API usage.</p>
      ) : (
        <div className='space-y-8'>
          <section>
            <h2 className='mb-3 text-lg font-semibold'>Recent requests</h2>
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
                    <TableRow>
                      <TableCell colSpan={6} className='text-muted-foreground'>
                        No API requests yet.
                      </TableCell>
                    </TableRow>
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
          </section>
          <section>
            <h2 className='mb-3 text-lg font-semibold'>API keys</h2>
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
                    <TableRow>
                      <TableCell colSpan={6} className='text-muted-foreground'>
                        No API keys yet.
                      </TableCell>
                    </TableRow>
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
          </section>
          <section>
            <h2 className='mb-3 text-lg font-semibold'>Webhook deliveries</h2>
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
                  {webhookDeliveries.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className='text-muted-foreground'>
                        No customer webhook posts yet.
                      </TableCell>
                    </TableRow>
                  ) : (
                    webhookDeliveries.map((row) => (
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
          </section>
        </div>
      )}
    </AdminPage>
  )
}
