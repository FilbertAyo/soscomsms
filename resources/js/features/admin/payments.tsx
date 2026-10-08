import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { adminPaymentsQueryKey, fetchAdminPayments } from '@/lib/credits'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { StatusBadge } from '@/components/soscom/status-badge'
import { ListPagination } from '@/components/soscom/list-pagination'
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

export function AdminPayments() {
  const [page, setPage] = useState(1)
  const paymentsQuery = useQuery({
    queryKey: [...adminPaymentsQueryKey, page],
    queryFn: () => fetchAdminPayments(page),
  })
  const payments = paymentsQuery.data?.payments ?? []
  const meta = paymentsQuery.data?.meta

  return (
    <AdminPage
      title='Payments'
      description='Mobile money purchases. SMS are added when the customer approves the prompt on their phone.'
    >
      {paymentsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load payments.</p>
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>SMS</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead>Network</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Requested</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {payments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className='text-muted-foreground'>
                    No purchases yet.
                  </TableCell>
                </TableRow>
              ) : (
                payments.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <p className='font-medium'>
                        {item.customer?.businessName ?? item.customer?.name}
                      </p>
                      <p className='text-xs text-muted-foreground'>
                        {item.customer?.email}
                      </p>
                    </TableCell>
                    <TableCell>{formatNumber(item.quantity)}</TableCell>
                    <TableCell>{formatTZS(item.amountTzs)}</TableCell>
                    <TableCell>{item.payerReference}</TableCell>
                    <TableCell className='capitalize'>{item.provider ?? '—'}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                    <TableCell>
                      {item.createdAt ? formatDateTime(item.createdAt) : '—'}
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
