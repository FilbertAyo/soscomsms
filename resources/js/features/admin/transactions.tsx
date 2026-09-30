import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  adminCustomersQueryKey,
  adminTransactionsQueryKey,
  fetchAdminTransactions,
  reverseCreditTransaction,
} from '@/lib/credits'
import { applyValidationErrors } from '@/lib/auth'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { Button } from '@/components/ui/button'
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

export function AdminTransactions() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const transactionsQuery = useQuery({
    queryKey: [...adminTransactionsQueryKey, page],
    queryFn: () => fetchAdminTransactions(page),
  })
  const reverseMutation = useMutation({
    mutationFn: reverseCreditTransaction,
    onSuccess: async () => {
      toast.success('Reversal recorded. The original row was not changed.')
      void queryClient.invalidateQueries({ queryKey: adminTransactionsQueryKey })
      void queryClient.invalidateQueries({ queryKey: adminCustomersQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  return (
    <AdminPage
      title='Transactions'
      description='Append-only credit ledger. Refunds and corrections add a new reversing row.'
    >
      {transactionsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load transactions.</p>
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>SMS</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Reference</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(transactionsQuery.data?.transactions ?? []).map((item) => (
                <TableRow key={item.id}>
                  <TableCell>
                    {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                  </TableCell>
                  <TableCell>
                    <p className='font-medium'>{item.customer?.name ?? '—'}</p>
                    <p className='text-xs text-muted-foreground'>
                      {item.customer?.email}
                    </p>
                  </TableCell>
                  <TableCell>{item.description}</TableCell>
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
                  <TableCell>{item.reference}</TableCell>
                  <TableCell className='text-end'>
                    {item.type !== 'reversal' && !item.reversed ? (
                      <Button
                        size='sm'
                        variant='outline'
                        disabled={reverseMutation.isPending}
                        onClick={() => reverseMutation.mutate(item.id)}
                      >
                        Reverse
                      </Button>
                    ) : null}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableWrap>
      )}
      {transactionsQuery.data?.meta ? (
        <ListPagination
          meta={transactionsQuery.data.meta}
          onPageChange={setPage}
        />
      ) : null}
    </AdminPage>
  )
}
