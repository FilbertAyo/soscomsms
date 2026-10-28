import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Building2, CalendarDays, Mail, Phone, ArrowLeft } from 'lucide-react'
import { toast } from 'sonner'
import {
  adminCustomerQueryKey,
  adminCustomersSearchQueryKey,
  adminLogsQueryKey,
  adminOverviewQueryKey,
  fetchAdminCustomer,
  suspendAdminCustomer,
  unsuspendAdminCustomer,
} from '@/lib/admin'
import {
  adminCustomerPricesQueryKey,
  adminCustomersQueryKey,
  adminPricingTiersQueryKey,
  adminTransactionsQueryKey,
  fetchAdminCustomerPrices,
  fetchAdminPricingTiers,
  grantCustomerCredits,
  quoteVolumeFromTiers,
  removeAdminCustomerPrice,
  saveAdminCustomerPrice,
} from '@/lib/credits'
import { applyValidationErrors } from '@/lib/auth'
import { normalizeError } from '@/lib/api-error'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { EmptyState } from '@/components/soscom/empty-state'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { StatusBadge } from '@/components/soscom/status-badge'
import {
  CardSkeletonGrid,
  PanelSkeleton,
} from '@/components/soscom/skeletons'
import { AdminPage } from './page'

export function AdminCustomerDetail({ customerId }: { customerId: string }) {
  const queryClient = useQueryClient()
  const [grantOpen, setGrantOpen] = useState(false)
  const [suspendOpen, setSuspendOpen] = useState(false)
  const [quantity, setQuantity] = useState('1000')
  const [unitPrice, setUnitPrice] = useState('')
  const [amount, setAmount] = useState('')
  const [note, setNote] = useState('')

  const customerQuery = useQuery({
    queryKey: adminCustomerQueryKey(customerId),
    queryFn: () => fetchAdminCustomer(Number(customerId)),
    enabled: /^\d+$/.test(customerId),
  })
  const pricingQuery = useQuery({
    queryKey: adminPricingTiersQueryKey,
    queryFn: fetchAdminPricingTiers,
    enabled: grantOpen,
  })

  const customer = customerQuery.data
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

  const invalidateCustomer = async () => {
    void queryClient.invalidateQueries({ queryKey: adminCustomerQueryKey(customerId) })
    void queryClient.invalidateQueries({ queryKey: adminCustomersQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminCustomersSearchQueryKey('') })
    void queryClient.invalidateQueries({ queryKey: adminOverviewQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminTransactionsQueryKey })
    void queryClient.invalidateQueries({ queryKey: adminPricingTiersQueryKey })
  }

  const grantMutation = useMutation({
    mutationFn: () =>
      grantCustomerCredits(
        Number(customerId),
        grantQuantity,
        note.trim(),
        grantAmount,
        grantUnitPrice
      ),
    onSuccess: async (result) => {
      toast.success(
        `Granted ${formatNumber(grantQuantity)} SMS for ${formatTZS(result.transaction.amountTzs ?? grantAmount)}. Balance is now ${formatNumber(result.smsBalance)}.`
      )
      setGrantOpen(false)
      resetGrantForm()
      await invalidateCustomer()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const suspendMutation = useMutation({
    mutationFn: () =>
      customer?.status === 'suspended'
        ? unsuspendAdminCustomer(Number(customerId))
        : suspendAdminCustomer(Number(customerId)),
    onSuccess: async (updated) => {
      toast.success(
        updated.status === 'suspended'
          ? `${updated.name ?? 'Customer'} is suspended.`
          : `${updated.name ?? 'Customer'} is active again.`
      )
      setSuspendOpen(false)
      queryClient.setQueryData(adminCustomerQueryKey(customerId), updated)
      await invalidateCustomer()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const missingCustomer =
    !/^\d+$/.test(customerId) ||
    (customerQuery.isError &&
      normalizeError(customerQuery.error).status === 404)

  if (missingCustomer) {
    return (
      <AdminPage title='Customer' description='This customer could not be found.'>
        <EmptyState
          title='Customer not found'
          description='This customer does not exist or is no longer available.'
          actionLabel='Back to customers'
          actionTo='/admin/customers'
        />
      </AdminPage>
    )
  }

  if (customerQuery.isError) {
    return (
      <AdminPage title='Customer' description='Unable to load customer details.'>
        <QueryLoadError
          message="We couldn't load this customer."
          onRetry={() => void customerQuery.refetch()}
        />
      </AdminPage>
    )
  }

  const initials = (customer?.name ?? customer?.businessName ?? 'C')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')

  return (
    <AdminPage
      title={customer?.name ?? 'Customer'}
      description={
        customer
          ? [customer.businessName, customer.email].filter(Boolean).join(' · ') ||
            'Customer account details'
          : 'Loading customer…'
      }
      actions={
        <div className='flex flex-wrap gap-2'>
          <Button variant='outline' asChild>
            <Link to='/admin/customers'>
              <ArrowLeft className='size-4' />
              All customers
            </Link>
          </Button>
          {customer ? (
            <>
              <Button
                onClick={() => {
                  resetGrantForm()
                  setGrantOpen(true)
                }}
              >
                Grant SMS
              </Button>
              <Button
                variant={customer.status === 'suspended' ? 'outline' : 'destructive'}
                onClick={() => setSuspendOpen(true)}
              >
                {customer.status === 'suspended' ? 'Unsuspend' : 'Suspend'}
              </Button>
            </>
          ) : null}
        </div>
      }
    >
      {customer ? (
        <div className='space-y-6'>
          <section className='overflow-hidden rounded-2xl border bg-card'>
            <div className='border-b bg-muted/40 px-5 py-6 sm:px-8'>
              <div className='flex flex-col gap-5 sm:flex-row sm:items-center'>
                <div className='flex size-16 shrink-0 items-center justify-center rounded-2xl bg-primary text-xl font-semibold text-primary-foreground'>
                  {initials || 'C'}
                </div>
                <div className='min-w-0 flex-1 space-y-2'>
                  <div className='flex flex-wrap items-center gap-3'>
                    <h2 className='truncate text-xl font-semibold tracking-tight'>
                      {customer.name ?? 'Unnamed customer'}
                    </h2>
                    <StatusBadge status={customer.status} />
                  </div>
                  <p className='text-sm text-muted-foreground'>
                    Customer #{customer.id}
                    {customer.createdAt
                      ? ` · Joined ${formatDate(customer.createdAt)}`
                      : null}
                  </p>
                </div>
              </div>
            </div>
            <div className='grid gap-0 sm:grid-cols-2 lg:grid-cols-4'>
              <InfoTile
                icon={<Mail className='size-4' />}
                label='Email'
                value={customer.email ?? '—'}
              />
              <InfoTile
                icon={<Phone className='size-4' />}
                label='Phone'
                value={customer.phone ?? '—'}
              />
              <InfoTile
                icon={<Building2 className='size-4' />}
                label='Business'
                value={customer.businessName ?? '—'}
              />
              <InfoTile
                icon={<CalendarDays className='size-4' />}
                label='Joined'
                value={customer.createdAt ? formatDate(customer.createdAt) : '—'}
              />
            </div>
          </section>

          <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-3'>
            <Stat
              label='SMS balance'
              value={formatNumber(customer.smsBalance)}
              hint='Credits available to send'
            />
            <Stat
              label='Sent this month'
              value={formatNumber(customer.sentThisMonth)}
              hint='Segments billed this month'
            />
            <Stat
              label='Campaigns'
              value={
                customer.campaignCount == null
                  ? '—'
                  : formatNumber(customer.campaignCount)
              }
              hint='All-time campaign count'
            />
          </div>

          <SpecialPrices customerId={customerId} />

          <section className='rounded-2xl border bg-card p-5 sm:p-6'>
            <h3 className='text-sm font-semibold tracking-tight'>Admin actions</h3>
            <p className='mt-1 max-w-2xl text-sm text-muted-foreground'>
              Grant SMS credits at a custom price, or change this account’s access
              status.
            </p>
            <div className='mt-4 flex flex-wrap gap-2'>
              <Button
                onClick={() => {
                  resetGrantForm()
                  setGrantOpen(true)
                }}
              >
                Grant SMS
              </Button>
              <Button
                variant={customer.status === 'suspended' ? 'outline' : 'destructive'}
                onClick={() => setSuspendOpen(true)}
              >
                {customer.status === 'suspended' ? 'Unsuspend account' : 'Suspend account'}
              </Button>
            </div>
          </section>
        </div>
      ) : (
        <div className='space-y-6'>
          <PanelSkeleton lines={4} />
          <CardSkeletonGrid count={3} />
        </div>
      )}

      <Dialog
        open={grantOpen}
        onOpenChange={(open) => {
          setGrantOpen(open)
          if (!open) resetGrantForm()
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Grant SMS to {customer?.name ?? 'customer'}</DialogTitle>
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
            <Button variant='outline' onClick={() => setGrantOpen(false)}>
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

      <ConfirmDialog
        open={suspendOpen}
        onOpenChange={setSuspendOpen}
        title={
          customer?.status === 'suspended'
            ? 'Unsuspend this customer?'
            : 'Suspend this customer?'
        }
        desc={
          customer?.status === 'suspended'
            ? 'They will be able to sign in and send SMS again, based on their profile status.'
            : 'They will not be able to send SMS until you unsuspend the account.'
        }
        confirmText={
          suspendMutation.isPending
            ? customer?.status === 'suspended'
              ? 'Unsuspending…'
              : 'Suspending…'
            : customer?.status === 'suspended'
              ? 'Unsuspend'
              : 'Suspend'
        }
        destructive={customer?.status !== 'suspended'}
        isLoading={suspendMutation.isPending}
        handleConfirm={() => suspendMutation.mutate()}
      />
    </AdminPage>
  )
}

function SpecialPrices({ customerId }: { customerId: string }) {
  const queryClient = useQueryClient()
  const [tierId, setTierId] = useState('')
  const [unitPrice, setUnitPrice] = useState('')
  const [expiresOn, setExpiresOn] = useState('')
  const [removeId, setRemoveId] = useState<number | null>(null)
  const pricesQuery = useQuery({
    queryKey: adminCustomerPricesQueryKey(customerId),
    queryFn: () => fetchAdminCustomerPrices(Number(customerId)),
    enabled: /^\d+$/.test(customerId),
  })
  const packages = pricesQuery.data?.packages ?? []
  const selected =
    packages.find((item) => String(item.tierId) === tierId) ?? packages[0] ?? null
  const parsedUnit = Number.parseInt(unitPrice, 10)
  const unitIsValid = Number.isFinite(parsedUnit) && parsedUnit >= 1 && parsedUnit <= 1000
  const exampleQuantity = selected?.minQuantity ?? 0
  const exampleTotal = unitIsValid ? exampleQuantity * parsedUnit : 0
  const normalTotal = selected ? exampleQuantity * selected.listUnitPriceTzs : 0
  const currentExpiry = selected?.price?.expiresAt?.slice(0, 10) ?? ''
  const unchanged =
    selected?.price != null &&
    selected.price.unitPriceTzs === parsedUnit &&
    currentExpiry === expiresOn

  function applyPackage(nextId: string, source = packages) {
    setTierId(nextId)
    const pack = source.find((item) => String(item.tierId) === nextId)
    setUnitPrice(pack?.price ? String(pack.price.unitPriceTzs) : '')
    setExpiresOn(pack?.price?.expiresAt ? pack.price.expiresAt.slice(0, 10) : '')
  }

  useEffect(() => {
    if (tierId !== '' || packages.length === 0) {
      return
    }

    const pack = packages[0]
    setTierId(String(pack.tierId))
    setUnitPrice(pack.price ? String(pack.price.unitPriceTzs) : '')
    setExpiresOn(pack.price?.expiresAt ? pack.price.expiresAt.slice(0, 10) : '')
  }, [packages, tierId])

  const saveMutation = useMutation({
    mutationFn: () =>
      saveAdminCustomerPrice(Number(customerId), {
        pricing_tier_id: selected?.tierId ?? 0,
        unit_price_tzs: parsedUnit,
        expires_at: expiresOn || null,
      }),
    onSuccess: async (pack) => {
      toast.success(
        `Special price saved: ${formatTZS(pack.price?.unitPriceTzs ?? parsedUnit)} per SMS for ${pack.label}.`
      )
      await queryClient.invalidateQueries({
        queryKey: adminCustomerPricesQueryKey(customerId),
      })
      void queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const removeMutation = useMutation({
    mutationFn: (priceId: number) =>
      removeAdminCustomerPrice(Number(customerId), priceId),
    onSuccess: async () => {
      toast.success('Special price removed. This customer pays the normal price again.')
      setRemoveId(null)
      setUnitPrice('')
      setExpiresOn('')
      await queryClient.invalidateQueries({
        queryKey: adminCustomerPricesQueryKey(customerId),
      })
      void queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const activeTierId = tierId || (selected ? String(selected.tierId) : '')

  return (
    <section className='rounded-2xl border bg-card p-5 sm:p-6'>
      <h3 className='text-sm font-semibold tracking-tight'>Special SMS prices</h3>
      <p className='mt-1 max-w-2xl text-sm text-muted-foreground'>
        An agreed price applies only to this customer and only to the selected
        SMS package. Everyone else keeps paying the normal price.
      </p>

      {pricesQuery.isError ? (
        <div className='mt-4'>
          <QueryLoadError
            message="We couldn't load special prices."
            onRetry={() => void pricesQuery.refetch()}
          />
        </div>
      ) : pricesQuery.isLoading ? (
        <p className='mt-4 text-sm text-muted-foreground'>Loading prices…</p>
      ) : packages.length === 0 ? (
        <p className='mt-4 text-sm text-muted-foreground'>No SMS packages are configured.</p>
      ) : (
        <div className='mt-4 space-y-4'>
          {packages.some((item) => item.price) ? (
            <div className='divide-y rounded-xl border'>
              {packages
                .filter((item) => item.price)
                .map((item) => (
                  <div
                    key={item.tierId}
                    className='flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between'
                  >
                    <div>
                      <p className='text-sm font-medium'>{item.label}</p>
                      <p className='text-sm text-muted-foreground'>
                        Agreed {formatTZS(item.price?.unitPriceTzs ?? 0)} per SMS
                        {' · '}
                        normal {formatTZS(item.listUnitPriceTzs)}
                        {item.price?.expiresAt
                          ? ` · until ${formatDate(item.price.expiresAt)}`
                          : ''}
                        {item.price && !item.price.active ? ' · expired' : ''}
                      </p>
                    </div>
                    <Button
                      variant='outline'
                      size='sm'
                      onClick={() => setRemoveId(item.price?.id ?? null)}
                    >
                      Remove
                    </Button>
                  </div>
                ))}
            </div>
          ) : (
            <p className='text-sm text-muted-foreground'>
              No special price. This customer pays the normal package price.
            </p>
          )}

          <div className='grid gap-4 sm:grid-cols-2'>
            <div className='space-y-2'>
              <Label>SMS package</Label>
              <Select
                value={activeTierId}
                onValueChange={(value) => applyPackage(value)}
              >
                <SelectTrigger className='w-full'>
                  <SelectValue placeholder='Select a package' />
                </SelectTrigger>
                <SelectContent>
                  {packages.map((item) => (
                    <SelectItem key={item.tierId} value={String(item.tierId)}>
                      {item.label} · {formatTZS(item.listUnitPriceTzs)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='space-y-2'>
              <Label htmlFor='special-unit-price'>Agreed price per SMS (TZS)</Label>
              <Input
                id='special-unit-price'
                type='number'
                min={1}
                max={1000}
                value={unitPrice}
                onChange={(event) => setUnitPrice(event.target.value)}
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='special-expires'>Expires (optional)</Label>
              <Input
                id='special-expires'
                type='date'
                value={expiresOn}
                onChange={(event) => setExpiresOn(event.target.value)}
              />
            </div>
          </div>

          {selected && unitIsValid ? (
            <p className='text-sm text-muted-foreground'>
              {formatNumber(exampleQuantity)} SMS at this price is {formatTZS(exampleTotal)}.
              The normal total is {formatTZS(normalTotal)}.
              {exampleTotal < 500
                ? ' Mobile money payments under TZS 500 are not accepted.'
                : ''}
            </p>
          ) : null}

          <Button
            disabled={
              saveMutation.isPending ||
              !selected ||
              !unitIsValid ||
              unchanged
            }
            onClick={() => saveMutation.mutate()}
          >
            {saveMutation.isPending
              ? 'Saving…'
              : selected?.price
                ? 'Update special price'
                : 'Save special price'}
          </Button>
        </div>
      )}

      <ConfirmDialog
        open={removeId !== null}
        onOpenChange={(open) => {
          if (!open) setRemoveId(null)
        }}
        title='Remove this special price?'
        desc='This customer will pay the normal package price on the next purchase. Past purchases keep the price they were charged.'
        confirmText={removeMutation.isPending ? 'Removing…' : 'Remove price'}
        destructive
        isLoading={removeMutation.isPending}
        handleConfirm={() => {
          if (removeId !== null) removeMutation.mutate(removeId)
        }}
      />
    </section>
  )
}

function Stat({
  label,
  value,
  hint,
}: {
  label: string
  value: string
  hint: string
}) {
  return (
    <div className='rounded-2xl border bg-card p-5'>
      <p className='text-sm text-muted-foreground'>{label}</p>
      <p className='mt-2 text-3xl font-semibold tracking-tight'>{value}</p>
      <p className='mt-2 text-xs text-muted-foreground'>{hint}</p>
    </div>
  )
}

function InfoTile({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode
  label: string
  value: string
}) {
  return (
    <div className='border-t px-5 py-4 sm:border-t-0 sm:border-l sm:px-6 sm:first:border-l-0'>
      <div className='flex items-center gap-2 text-muted-foreground'>
        {icon}
        <p className='text-xs font-medium uppercase tracking-wide'>{label}</p>
      </div>
      <p className='mt-2 truncate text-sm font-medium'>{value}</p>
    </div>
  )
}
