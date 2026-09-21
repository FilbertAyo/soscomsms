import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  adminDeliveryReportsQueryKey,
  fetchAdminDeliveryReports,
} from '@/lib/admin'
import { formatDateTime } from '@/lib/format'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { ListPagination } from '@/components/soscom/list-pagination'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AdminPage } from './page'

export function AdminReports() {
  const [page, setPage] = useState(1)
  const reportsQuery = useQuery({
    queryKey: [...adminDeliveryReportsQueryKey, page],
    queryFn: () => fetchAdminDeliveryReports(page),
  })
  const reports = reportsQuery.data?.reports ?? []
  const meta = reportsQuery.data?.meta

  return (
    <AdminPage
      title='Delivery Reports'
      description='Platform-wide message status from live campaigns.'
    >
      {reportsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load delivery reports.</p>
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Message ID</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Recipient</TableHead>
                <TableHead>Sender ID</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Sent At</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {reports.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className='text-muted-foreground'>
                    No delivery reports yet.
                  </TableCell>
                </TableRow>
              ) : (
                reports.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell className='font-mono text-sm'>
                      {item.providerMessageId ?? item.id}
                    </TableCell>
                    <TableCell>
                      <p>{item.customerName ?? '—'}</p>
                      <p className='text-xs text-muted-foreground'>
                        {item.customerEmail}
                      </p>
                    </TableCell>
                    <TableCell>{item.phone}</TableCell>
                    <TableCell>{item.senderId}</TableCell>
                    <TableCell>
                      {item.status ? <StatusBadge status={item.status} /> : '—'}
                    </TableCell>
                    <TableCell>
                      {item.sentAt ? formatDateTime(item.sentAt) : '—'}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableWrap>
      )}
      {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
    </AdminPage>
  )
}
