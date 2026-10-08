import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import { fetchPayments, paymentsQueryKey } from '@/lib/credits'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/soscom/status-badge'
import { ListPagination } from '@/components/soscom/list-pagination'
import { TableSkeleton } from '@/components/soscom/skeletons'
import { TableWrap } from '@/components/soscom/table-wrap'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ContentSection } from '../components/content-section'

export function SettingsBilling() {
  const balance = useAuthStore((state) => state.user?.customer?.smsBalance ?? 0)
  const [page, setPage] = useState(1)
  const paymentsQuery = useQuery({
    queryKey: [...paymentsQueryKey, page],
    queryFn: () => fetchPayments(page),
  })
  const payments = paymentsQuery.data?.payments ?? []
  const meta = paymentsQuery.data?.meta

  return (
    <ContentSection
      title='Billing'
      desc='Purchase history for SMS credits.'
    >
      <div className='space-y-6'>
        <p className='text-sm'>
          Current SMS Balance:{' '}
          <span className='font-semibold'>{formatNumber(balance)} SMS</span>
        </p>
        <p className='text-sm text-muted-foreground'>
          Buy SMS with mobile money. Credits appear after you approve the
          prompt on your phone.
        </p>
        <div className='flex flex-wrap gap-2'>
          <Button asChild>
            <Link to='/wallet/buy'>Buy SMS</Link>
          </Button>
          <Button variant='outline' asChild>
            <Link to='/wallet/transactions'>View Transactions</Link>
          </Button>
        </div>
        {paymentsQuery.isError ? (
          <p className='text-sm text-destructive'>Could not load payments.</p>
        ) : paymentsQuery.isLoading ? (
          <TableSkeleton rows={4} />
        ) : payments.length === 0 ? (
          <p className='text-sm text-muted-foreground'>No purchase requests yet.</p>
        ) : (
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>SMS</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Payer</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {payments.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                    </TableCell>
                    <TableCell>{formatNumber(item.quantity)}</TableCell>
                    <TableCell>{formatTZS(item.amountTzs)}</TableCell>
                    <TableCell>{item.payerReference}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableWrap>
        )}
        {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
      </div>
    </ContentSection>
  )
}
