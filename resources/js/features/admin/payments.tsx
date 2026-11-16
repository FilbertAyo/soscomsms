import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { adminPaymentsQueryKey, fetchAdminPayments } from '@/lib/credits'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { ReceiptDownloadButton } from '@/components/soscom/receipt-download-button'
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
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { type PaymentRecord } from '@/types/payments'
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
      description='Mobile money purchases and SMS granted by hand.'
    >
      {paymentsQuery.isError ? (
        <QueryLoadError
          message="We couldn't load payments."
          onRetry={() => void paymentsQuery.refetch()}
        />
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
                <TableHead className='text-end'>Receipt</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {payments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className='text-muted-foreground'>
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
                    <TableCell>
                      <p>{formatTZS(item.amountTzs)}</p>
                      <p className='text-xs text-muted-foreground'>
                        {formatTZS(item.unitPriceTzs)} each
                        {item.specialPrice && item.listUnitPriceTzs != null
                          ? ` · normal ${formatTZS(item.listUnitPriceTzs)}`
                          : ''}
                      </p>
                      {item.snippeReference ? (
                        <p className='text-xs text-muted-foreground'>
                          {item.snippeReference}
                        </p>
                      ) : null}
                      {item.externalReference ? (
                        <p className='text-xs text-muted-foreground'>
                          {item.externalReference}
                        </p>
                      ) : null}
                    </TableCell>
                    <TableCell>{item.payerReference}</TableCell>
                    <TableCell className='capitalize'>{paymentChannel(item)}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                    <TableCell>
                      {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                    </TableCell>
                    <TableCell className='text-end'>
                      {item.status === 'paid' ? (
                        <ReceiptDownloadButton paymentId={item.id} admin />
                      ) : null}
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

function paymentChannel(item: PaymentRecord): string {
  if (item.provider) {
    return item.provider
  }

  switch (item.method) {
    case 'cash':
      return 'Cash'
    case 'bank':
      return 'Bank'
    case 'manual_mobile':
      return 'Mobile money'
    case 'snippe':
    case 'lipa_namba':
      return '—'
    default: {
      const exhaustive: never = item.method
      return exhaustive
    }
  }
}
