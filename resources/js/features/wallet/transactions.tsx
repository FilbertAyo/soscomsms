import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Receipt } from 'lucide-react'
import {
  creditTransactionsQueryKey,
  fetchCreditTransactions,
} from '@/lib/credits'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { TableSkeleton } from '@/components/soscom/skeletons'
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

export function TransactionsPage() {
  const [page, setPage] = useState(1)
  const transactionsQuery = useQuery({
    queryKey: [...creditTransactionsQueryKey, page],
    queryFn: () => fetchCreditTransactions(page),
  })
  const transactions = transactionsQuery.data?.transactions ?? []
  const meta = transactionsQuery.data?.meta

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Transactions'
       />
        {transactionsQuery.isError ? (
          <p className='text-sm text-destructive'>
            Could not load transactions. Refresh and try again.
          </p>
        ) : transactionsQuery.isLoading ? (
          <TableSkeleton />
        ) : transactions.length === 0 ? (
          <EmptyState
            icon={Receipt}
            title='No purchases yet'
            description='Your signup bonus and later SMS purchases will appear here.'
            actionLabel='Buy SMS'
            actionTo='/wallet/buy'
          />
        ) : (
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>SMS</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Type</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {transactions.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                    </TableCell>
                    <TableCell className='font-medium'>
                      {item.description}
                      {item.reversed ? (
                        <span className='ms-2 text-xs text-muted-foreground'>
                          Reversed
                        </span>
                      ) : null}
                    </TableCell>
                    <TableCell>
                      {item.quantity > 0 ? '+' : ''}
                      {formatNumber(item.quantity)}
                    </TableCell>
                    <TableCell>
                      {item.amountTzs ? formatTZS(item.amountTzs) : '—'}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={item.type} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableWrap>
        )}
        {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
      </Main>
    </>
  )
}
