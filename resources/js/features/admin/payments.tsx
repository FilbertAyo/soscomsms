import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  adminCustomersQueryKey,
  adminPaymentsQueryKey,
  adminTransactionsQueryKey,
  confirmAdminPayment,
  fetchAdminPayments,
  rejectAdminPayment,
  walletQueryKey,
} from '@/lib/credits'
import { applyValidationErrors } from '@/lib/auth'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import { type PaymentRecord } from '@/types/payments'
import { AdminPage } from './page'

export function AdminPayments() {
  const queryClient = useQueryClient()
  const [rejectTarget, setRejectTarget] = useState<PaymentRecord | null>(null)
  const [reason, setReason] = useState('')
  const [page, setPage] = useState(1)
  const paymentsQuery = useQuery({
    queryKey: [...adminPaymentsQueryKey, page],
    queryFn: () => fetchAdminPayments(page),
  })
  const payments = paymentsQuery.data?.payments ?? []
  const meta = paymentsQuery.data?.meta
  const lipaNamba = paymentsQuery.data?.lipaNamba ?? '123456'
  const lipaNambaName =
    paymentsQuery.data?.lipaNambaName ?? 'SOSCOM TECHNOLOGIES COMPANY LIMITED'

  const confirmMutation = useMutation({
    mutationFn: (id: number) => confirmAdminPayment(id),
    onSuccess: async (result) => {
      toast.success(
        `Marked paid. Customer balance is now ${formatNumber(result.smsBalance ?? 0)} SMS.`
      )
      await queryClient.invalidateQueries({ queryKey: adminPaymentsQueryKey })
      await queryClient.invalidateQueries({ queryKey: adminTransactionsQueryKey })
      await queryClient.invalidateQueries({ queryKey: adminCustomersQueryKey })
      await queryClient.invalidateQueries({ queryKey: walletQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const rejectMutation = useMutation({
    mutationFn: () =>
      rejectAdminPayment(rejectTarget?.id ?? 0, reason.trim()),
    onSuccess: async () => {
      toast.success('Payment rejected. No credits were issued.')
      setRejectTarget(null)
      setReason('')
      await queryClient.invalidateQueries({ queryKey: adminPaymentsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  return (
    <AdminPage
      title='Payments'
      description={`Lipa namba ${lipaNamba} (${lipaNambaName}). SMS are added only when you mark a request paid.`}
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
                <TableHead>Payer</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Requested</TableHead>
                <TableHead className='text-end'>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {payments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className='text-muted-foreground'>
                    No purchase requests yet.
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
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                    <TableCell>
                      {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                    </TableCell>
                    <TableCell className='space-x-2 text-end'>
                      {item.status === 'pending' ? (
                        <>
                          <Button
                            size='sm'
                            onClick={() => confirmMutation.mutate(item.id)}
                            disabled={confirmMutation.isPending}
                          >
                            Mark paid
                          </Button>
                          <Button
                            size='sm'
                            variant='outline'
                            onClick={() => {
                              setRejectTarget(item)
                              setReason('')
                            }}
                          >
                            Reject
                          </Button>
                        </>
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
      <Dialog
        open={rejectTarget !== null}
        onOpenChange={(open) => !open && setRejectTarget(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject this payment?</DialogTitle>
          </DialogHeader>
          <div className='space-y-2'>
            <Label htmlFor='reject-reason'>Reason</Label>
            <Input
              id='reject-reason'
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setRejectTarget(null)}>
              Cancel
            </Button>
            <Button
              variant='destructive'
              disabled={rejectMutation.isPending || reason.trim() === ''}
              onClick={() => rejectMutation.mutate()}
            >
              {rejectMutation.isPending ? 'Rejecting…' : 'Reject'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AdminPage>
  )
}
