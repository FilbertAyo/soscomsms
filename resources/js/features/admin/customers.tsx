import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  adminCustomersSearchQueryKey,
  adminLogsQueryKey,
  adminOverviewQueryKey,
  suspendAdminCustomer,
  unsuspendAdminCustomer,
} from '@/lib/admin'
import {
  adminCustomersQueryKey,
  adminPricingTiersQueryKey,
  adminTransactionsQueryKey,
  fetchAdminCustomers,
  fetchAdminPricingTiers,
  grantCustomerCredits,
  quoteVolumeFromTiers,
} from '@/lib/credits'
import { applyValidationErrors } from '@/lib/auth'
import { formatDate, formatNumber, formatTZS } from '@/lib/format'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
import { type AdminCustomer } from '@/types/credits'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { AdminPage } from './page'

export function AdminCustomers() {
  const queryClient = useQueryClient()
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [viewing, setViewing] = useState<AdminCustomer | null>(null)
  const [target, setTarget] = useState<AdminCustomer | null>(null)
  const [quantity, setQuantity] = useState('1000')
  const [unitPrice, setUnitPrice] = useState('')
  const [amount, setAmount] = useState('')
  const [note, setNote] = useState('')
  const customersQuery = useQuery({
    queryKey: adminCustomersSearchQueryKey(`${q}:${page}`),
    queryFn: () => fetchAdminCustomers(q, page),
  })
  const pricingQuery = useQuery({
    queryKey: adminPricingTiersQueryKey,
    queryFn: fetchAdminPricingTiers,
  })
  const customers = customersQuery.data?.customers ?? []
  const meta = customersQuery.data?.meta
  const tiers = pricingQuery.data?.tiers ?? []
  const parsedQuantity = Number.parseInt(quantity, 10)
  const grantQuantity =
    Number.isFinite(parsedQuantity) && parsedQuantity > 0 ? parsedQuantity : 0
  const quote = useMemo(
    () => quoteVolumeFromTiers(grantQuantity, tiers),
    [grantQuantity, tiers]
  )
  const quotedUnit = quote?.unitPriceTzs ?? 0
  const quotedTotal = quote?.totalTzs ?? 0
  const unitPriceValue = unitPrice === '' ? String(quotedUnit || '') : unitPrice
  const amountValue = amount === '' ? String(quotedTotal || '') : amount
  const parsedUnitPrice = Number.parseInt(unitPriceValue, 10)
  const parsedAmount = Number.parseInt(amountValue, 10)
  const grantUnitPrice =
    Number.isFinite(parsedUnitPrice) && parsedUnitPrice >= 0 ? parsedUnitPrice : 0
  const grantAmount =
    Number.isFinite(parsedAmount) && parsedAmount >= 0 ? parsedAmount : 0
  const preferredPrice = quote !== null && grantAmount < quotedTotal

  function resetGrantForm() {
    setQuantity('1000')
    setUnitPrice('')
    setAmount('')
    setNote('')
  }

  const invalidateCustomers = async () => {
    void queryClient.invalidateQueries({ queryKey: adminCustomersQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminOverviewQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminTransactionsQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminPricingTiersQueryKey })
  }

  const grantMutation = useMutation({
    mutationFn: () =>
      grantCustomerCredits(
        target?.id ?? 0,
        grantQuantity,
        note.trim(),
        grantAmount,
        grantUnitPrice
      ),
    onSuccess: async (result) => {
      toast.success(
        `Granted ${formatNumber(grantQuantity)} SMS for ${formatTZS(result.transaction.amountTzs ?? grantAmount)}. Balance is now ${formatNumber(result.smsBalance)}.`
      )
      setTarget(null)
      resetGrantForm()
      await invalidateCustomers()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })
  const suspendMutation = useMutation({
    mutationFn: (customer: AdminCustomer) =>
      customer.status === 'suspended'
        ? unsuspendAdminCustomer(customer.id)
        : suspendAdminCustomer(customer.id),
    onSuccess: async (customer) => {
      toast.success(
        customer.status === 'suspended'
          ? `${customer.name ?? 'Customer'} is suspended.`
          : `${customer.name ?? 'Customer'} is active again.`
      )
      setViewing(customer)
      await invalidateCustomers()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  return (
    <AdminPage
      title='Customers'
      description='Search accounts, view balances, grant credits, and suspend or unsuspend customers.'
    >
      <div className='mb-4 max-w-md'>
        <Input
          value={q}
          onChange={(event) => {
            setQ(event.target.value)
            setPage(1)
          }}
          placeholder='Search name, email, business, or phone'
        />
      </div>
      {customersQuery.isError ? (
        <QueryLoadError
          message="We couldn't load customers."
          onRetry={() => void customersQuery.refetch()}
        />
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Business</TableHead>
                <TableHead>SMS Balance</TableHead>
                <TableHead>Sent this month</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='text-end'>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {customers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className='text-muted-foreground'>
                    {q ? 'No customers match that search.' : 'No customers yet.'}
                  </TableCell>
                </TableRow>
              ) : (
                customers.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <p className='font-medium'>{item.name}</p>
                      <p className='text-xs text-muted-foreground'>{item.email}</p>
                    </TableCell>
                    <TableCell>{item.businessName ?? '—'}</TableCell>
                    <TableCell>{formatNumber(item.smsBalance)}</TableCell>
                    <TableCell>{formatNumber(item.sentThisMonth)}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                    <TableCell className='text-end'>
                      <div className='flex justify-end gap-2'>
                        <Button
                          size='sm'
                          variant='ghost'
                          onClick={() => setViewing(item)}
                        >
                          View
                        </Button>
                        <Button
                          size='sm'
                          variant='outline'
                          onClick={() => {
                            setTarget(item)
                            resetGrantForm()
                          }}
                        >
                          Grant SMS
                        </Button>
                        <Button
                          size='sm'
                          variant={item.status === 'suspended' ? 'outline' : 'destructive'}
                          disabled={suspendMutation.isPending}
                          onClick={() => suspendMutation.mutate(item)}
                        >
                          {item.status === 'suspended' ? 'Unsuspend' : 'Suspend'}
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableWrap>
      )}
      {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}

      <Dialog open={viewing !== null} onOpenChange={(open) => !open && setViewing(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{viewing?.name ?? 'Customer'}</DialogTitle>
          </DialogHeader>
          <dl className='grid gap-3 text-sm'>
            <Detail label='Email' value={viewing?.email ?? '—'} />
            <Detail label='Business' value={viewing?.businessName ?? '—'} />
            <Detail label='Phone' value={viewing?.phone ?? '—'} />
            <Detail
              label='SMS balance'
              value={formatNumber(viewing?.smsBalance ?? 0)}
            />
            <Detail
              label='Sent this month'
              value={formatNumber(viewing?.sentThisMonth ?? 0)}
            />
            <Detail
              label='Joined'
              value={viewing?.createdAt ? formatDate(viewing.createdAt) : '—'}
            />
            <div className='flex items-center justify-between gap-3'>
              <dt className='text-muted-foreground'>Status</dt>
              <dd>
                {viewing ? <StatusBadge status={viewing.status} /> : '—'}
              </dd>
            </div>
          </dl>
          <DialogFooter>
            <Button variant='outline' onClick={() => setViewing(null)}>
              Close
            </Button>
            {viewing ? (
              <Button
                variant={viewing.status === 'suspended' ? 'outline' : 'destructive'}
                disabled={suspendMutation.isPending}
                onClick={() => suspendMutation.mutate(viewing)}
              >
                {viewing.status === 'suspended' ? 'Unsuspend' : 'Suspend'}
              </Button>
            ) : null}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={target !== null} onOpenChange={(open) => !open && setTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Grant SMS to {target?.name}</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='grant-quantity'>SMS quantity</Label>
              <Input
                id='grant-quantity'
                type='number'
                min={1}
                value={quantity}
                onChange={(event) => {
                  setQuantity(event.target.value)
                  setUnitPrice('')
                  setAmount('')
                }}
              />
            </div>
            {quote ? (
              <p className='text-sm text-muted-foreground'>
                Range price: {quote.tier.label} at {formatTZS(quote.unitPriceTzs)}{' '}
                each ({formatTZS(quote.totalTzs)}).
              </p>
            ) : grantQuantity > 0 && pricingQuery.isError ? (
              <QueryLoadError
                message="We couldn't load volume prices. You can still enter a price."
                onRetry={() => void pricingQuery.refetch()}
              />
            ) : null}
            <div className='grid gap-4 sm:grid-cols-2'>
              <div className='space-y-2'>
                <Label htmlFor='grant-unit-price'>Unit price (TZS)</Label>
                <Input
                  id='grant-unit-price'
                  type='number'
                  min={0}
                  value={unitPriceValue}
                  onChange={(event) => {
                    const nextUnit = event.target.value
                    setUnitPrice(nextUnit)
                    const unit = Number.parseInt(nextUnit, 10)
                    if (grantQuantity > 0 && Number.isFinite(unit) && unit >= 0) {
                      setAmount(String(grantQuantity * unit))
                    }
                  }}
                />
              </div>
              <div className='space-y-2'>
                <Label htmlFor='grant-amount'>Price for this quantity (TZS)</Label>
                <Input
                  id='grant-amount'
                  type='number'
                  min={0}
                  value={amountValue}
                  onChange={(event) => {
                    const nextAmount = event.target.value
                    setAmount(nextAmount)
                    const total = Number.parseInt(nextAmount, 10)
                    if (grantQuantity > 0 && Number.isFinite(total) && total >= 0) {
                      setUnitPrice(String(Math.floor(total / grantQuantity)))
                    }
                  }}
                />
              </div>
            </div>
            <p className='text-sm text-muted-foreground'>
              {preferredPrice
                ? `Preferred price — ${formatTZS(grantAmount)} will be added to revenue instead of the range total.`
                : 'This amount will be added to revenue. Lower it for a preferred customer.'}
            </p>
            <div className='space-y-2'>
              <Label htmlFor='grant-note'>Note (optional)</Label>
              <Input
                id='grant-note'
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder='Starter top-up'
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setTarget(null)}>
              Cancel
            </Button>
            <Button
              disabled={
                grantMutation.isPending ||
                grantQuantity < 1 ||
                !Number.isFinite(parsedAmount) ||
                parsedAmount < 0
              }
              onClick={() => grantMutation.mutate()}
            >
              {grantMutation.isPending ? 'Granting…' : 'Grant SMS'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AdminPage>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className='flex items-center justify-between gap-3'>
      <dt className='text-muted-foreground'>{label}</dt>
      <dd className='text-end font-medium'>{value}</dd>
    </div>
  )
}
