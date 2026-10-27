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
import { ConfirmDialog } from '@/components/confirm-dialog'
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
import { type CreditTransaction } from '@/types/credits'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { AdminPage } from './page'

export function AdminTransactions() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [reverseTarget, setReverseTarget] = useState<CreditTransaction | null>(
    null
  )
  const transactionsQuery = useQuery({
    queryKey: [...adminTransactionsQueryKey, page],
    queryFn: () => fetchAdminTransactions(page),
  })
  const reverseMutation = useMutation({
    mutationFn: reverseCreditTransaction,
    onSuccess: async () => {
      setReverseTarget(null)
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
        <QueryLoadError
          message="We couldn't load transactions."
          onRetry={() => void transactionsQuery.refetch()}
        />
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
                        onClick={() => setReverseTarget(item)}
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

      <ConfirmDialog
        open={reverseTarget !== null}
        onOpenChange={(open) => {
          if (!open && !reverseMutation.isPending) {
            setReverseTarget(null)
          }
        }}
        title='Reverse this transaction?'
        desc={
          reverseTarget ? (
            <div className='space-y-2'>
              <p>
                This adds a new row that cancels{' '}
                <span className='font-medium text-foreground'>
                  {reverseTarget.quantity > 0 ? '+' : ''}
                  {formatNumber(reverseTarget.quantity)} SMS
                </span>{' '}
                for{' '}
                <span className='font-medium text-foreground'>
                  {reverseTarget.customer?.name ?? 'this customer'}
                </span>
                . Their SMS balance changes straight away. The original row
                stays in the ledger and this cannot be undone.
              </p>
              <p className='text-xs'>
                {reverseTarget.description} · {reverseTarget.reference}
              </p>
            </div>
          ) : (
            ''
          )
        }
        cancelBtnText='Cancel'
        confirmText={reverseMutation.isPending ? 'Reversing…' : 'Reverse'}
        destructive
        isLoading={reverseMutation.isPending}
        handleConfirm={() => {
          if (reverseTarget) {
            reverseMutation.mutate(reverseTarget.id)
          }
        }}
      />
    </AdminPage>
  )
}
