import { useEffect, useMemo, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  createPayment,
  createQuote,
  fetchPayment,
  fetchPayments,
  fetchWallet,
  paymentsQueryKey,
  quantityInTier,
  quoteQueryKey,
  walletQueryKey,
  withSmsBalance,
} from '@/lib/credits'
import { formatNumber, formatTZS } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { AppHeader } from '@/components/soscom/app-header'
import { StatusBadge } from '@/components/soscom/status-badge'
import { Main } from '@/components/layout/main'
import { type PricingTier } from '@/types/credits'
import { type PaymentRecord } from '@/types/payments'

export function BuySmsPage() {
  const queryClient = useQueryClient()
  const profilePhone = useAuthStore((state) => state.user?.customer?.phone ?? '')
  const [quantityInput, setQuantityInput] = useState('')
  const [payerReference, setPayerReference] = useState(profilePhone)
  const [watchingId, setWatchingId] = useState<number | null>(null)
  const announced = useRef<number | null>(null)
  const walletQuery = useQuery({
    queryKey: walletQueryKey,
    queryFn: fetchWallet,
  })
  const paymentsQuery = useQuery({
    queryKey: paymentsQueryKey,
    queryFn: () => fetchPayments(1, 20),
  })
  const tiers = walletQuery.data?.tiers ?? []
  const minPurchaseQuantity = walletQuery.data?.minPurchaseQuantity ?? 100
  const pendingPayment = (paymentsQuery.data?.payments ?? []).find(
    (item) => item.status === 'pending'
  )
  const activeId = watchingId ?? pendingPayment?.id ?? null
  const paymentQuery = useQuery({
    queryKey: ['payments', 'status', activeId],
    queryFn: () => fetchPayment(activeId ?? 0),
    enabled: activeId !== null,
    refetchInterval: (query) =>
      query.state.data?.payment.status === 'pending' ? 3000 : false,
  })
  const activePayment = paymentQuery.data?.payment ?? pendingPayment ?? null
  const waiting = activePayment?.status === 'pending'
  const quantity = parsedSmsQuantity(quantityInput)
  const localQuote = useMemo(
    () => quoteFromTiers(quantity, tiers, minPurchaseQuantity),
    [minPurchaseQuantity, quantity, tiers]
  )
  const quoteQuery = useQuery({
    queryKey: quoteQueryKey(quantity),
    queryFn: () => createQuote(quantity),
    enabled: quantity >= minPurchaseQuantity && !waiting,
    retry: false,
  })
  const quote =
    quoteQuery.data?.quantity === quantity ? quoteQuery.data : localQuote
  const belowMinimum = quantity > 0 && quantity < minPurchaseQuantity

  useEffect(() => {
    const payment = paymentQuery.data?.payment
    const balance = paymentQuery.data?.smsBalance
    if (!payment || payment.status !== 'paid' || balance == null) {
      return
    }
    if (announced.current === payment.id) {
      return
    }
    announced.current = payment.id
    const current = useAuthStore.getState().user
    if (current) {
      useAuthStore.getState().setUser(withSmsBalance(current, balance))
    }
    toast.success(`${formatNumber(payment.quantity)} SMS added to your account.`)
    void queryClient.invalidateQueries({ queryKey: walletQueryKey })
    void queryClient.invalidateQueries({ queryKey: paymentsQueryKey })
  }, [paymentQuery.data, queryClient])

  const buyMutation = useMutation({
    mutationFn: () =>
      createPayment({
        quantity,
        payer_reference: payerReference.trim(),
      }),
    onSuccess: async (result) => {
      setWatchingId(result.payment.id)
      toast.success('Check your phone and approve the payment prompt.')
      void queryClient.invalidateQueries({ queryKey: paymentsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function selectTier(tier: PricingTier) {
    setQuantityInput(String(tier.minQuantity))
  }

  function pay() {
    if (quantity < minPurchaseQuantity) {
      toast.error(`Enter at least ${formatNumber(minPurchaseQuantity)} SMS.`)
      return
    }
    buyMutation.mutate()
  }

  function startAnother() {
    setWatchingId(null)
    setQuantityInput('')
    announced.current = null
    void queryClient.invalidateQueries({ queryKey: paymentsQueryKey })
  }

  return (
    <>
      <AppHeader />
      <Main>
        <div className='mx-auto w-full max-w-3xl'>
          <div className='rounded-2xl border bg-card p-6 shadow-sm sm:p-8'>
            <div className='mb-6'>
              <h1 className='text-2xl font-semibold tracking-tight'>Buy SMS</h1>
              <p className='mt-1 text-sm text-muted-foreground'>
                Pay with M-Pesa, Airtel Money, Mixx by Yas, or Halotel. Approve
                the prompt on your phone.
              </p>
            </div>

            {walletQuery.isError ? (
              <p className='text-sm text-destructive'>
                Could not load SMS prices. Refresh and try again.
              </p>
            ) : waiting && activePayment ? (
              <WaitingCard payment={activePayment} />
            ) : activePayment && activePayment.status !== 'pending' && watchingId === activePayment.id ? (
              <ResultCard payment={activePayment} onAgain={startAnother} />
            ) : (
              <div className='space-y-6'>
                {tiers.length > 0 ? (
                  <div className='grid gap-2 sm:grid-cols-3'>
                    {tiers.map((tier) => (
                      <button
                        key={tier.id}
                        type='button'
                        onClick={() => selectTier(tier)}
                        className={cn(
                          'rounded-xl border px-3 py-3 text-start transition-colors hover:bg-muted/40',
                          quantityInTier(quantity, tier) && 'border-primary bg-primary/5'
                        )}
                      >
                        <p className='text-sm font-medium'>{tier.label}</p>
                        <p className='mt-0.5 text-xs text-muted-foreground'>
                          {formatTZS(tier.unitPriceTzs)} each
                        </p>
                      </button>
                    ))}
                  </div>
                ) : null}

                <div className='space-y-2'>
                  <Label htmlFor='sms-quantity'>How many SMS?</Label>
                  <Input
                    id='sms-quantity'
                    type='number'
                    min={minPurchaseQuantity}
                    step={1}
                    inputMode='numeric'
                    value={quantityInput}
                    onChange={(event) => setQuantityInput(event.target.value)}
                  />
                  {belowMinimum ? (
                    <p className='text-sm text-destructive'>
                      Enter at least {formatNumber(minPurchaseQuantity)} SMS.
                    </p>
                  ) : (
                    <p className='text-xs text-muted-foreground'>
                      Minimum {formatNumber(minPurchaseQuantity)} SMS.
                    </p>
                  )}
                </div>

                <div className='rounded-xl bg-muted/40 px-4 py-4'>
                  <p className='text-sm text-muted-foreground'>Amount to pay</p>
                  <p className='mt-1 text-3xl font-semibold tracking-tight'>
                    {quote ? formatTZS(quote.totalTzs) : '—'}
                  </p>
                  {quote ? (
                    <p className='mt-1 text-sm text-muted-foreground'>
                      {formatNumber(quote.quantity)} SMS at {formatTZS(quote.unitPriceTzs)} each
                    </p>
                  ) : null}
                </div>

                <div className='space-y-2'>
                  <Label htmlFor='payer-reference'>Mobile number to approve the payment</Label>
                  <Input
                    id='payer-reference'
                    type='tel'
                    inputMode='tel'
                    autoComplete='tel'
                    placeholder='07XXXXXXXX'
                    value={payerReference}
                    onChange={(event) => setPayerReference(event.target.value)}
                  />
                  <p className='text-xs text-muted-foreground'>
                    This number receives the PIN prompt. It can be different from
                    the number on your account.
                  </p>
                </div>

                <Button
                  className='w-full'
                  disabled={
                    buyMutation.isPending ||
                    quantity < minPurchaseQuantity ||
                    !quote ||
                    payerReference.trim().length < 9
                  }
                  onClick={pay}
                >
                  {buyMutation.isPending ? 'Sending prompt…' : 'Pay with mobile money'}
                </Button>
              </div>
            )}
          </div>
        </div>
      </Main>
    </>
  )
}

function WaitingCard({ payment }: { payment: PaymentRecord }) {
  return (
    <div className='space-y-4'>
      <div className='rounded-xl bg-muted/40 px-4 py-4'>
        <p className='text-sm text-muted-foreground'>Waiting for approval</p>
        <p className='mt-1 text-3xl font-semibold tracking-tight'>
          {formatTZS(payment.amountTzs)}
        </p>
        <p className='mt-1 text-sm text-muted-foreground'>
          {formatNumber(payment.quantity)} SMS · prompt sent to {payment.payerReference}
        </p>
      </div>
      <p className='text-sm text-muted-foreground'>
        Open the prompt on that phone and enter your mobile-money PIN. This page
        updates when the payment is confirmed.
      </p>
      <StatusBadge status={payment.status} />
    </div>
  )
}

function ResultCard({
  payment,
  onAgain,
}: {
  payment: PaymentRecord
  onAgain: () => void
}) {
  const paid = payment.status === 'paid'

  return (
    <div className='space-y-4'>
      <div className='rounded-xl bg-muted/40 px-4 py-4'>
        <p className='text-sm text-muted-foreground'>
          {paid ? 'Payment confirmed' : 'Payment not completed'}
        </p>
        <p className='mt-1 text-3xl font-semibold tracking-tight'>
          {paid ? `${formatNumber(payment.quantity)} SMS` : formatTZS(payment.amountTzs)}
        </p>
        {payment.rejectionReason ? (
          <p className='mt-2 text-sm text-muted-foreground'>{payment.rejectionReason}</p>
        ) : null}
      </div>
      <StatusBadge status={payment.status} />
      <Button className='w-full' variant={paid ? 'default' : 'outline'} onClick={onAgain}>
        {paid ? 'Buy more SMS' : 'Try again'}
      </Button>
    </div>
  )
}

type QuotePreview = {
  quantity: number
  unitPriceTzs: number
  totalTzs: number
  tier: PricingTier
}

function parsedSmsQuantity(value: string): number {
  const quantity = Number.parseInt(value, 10)

  if (!Number.isFinite(quantity) || quantity < 1) {
    return 0
  }

  return quantity
}

function quoteFromTiers(
  quantity: number,
  tiers: PricingTier[],
  minPurchaseQuantity: number
): QuotePreview | null {
  if (quantity < minPurchaseQuantity || tiers.length === 0) {
    return null
  }

  const tier =
    tiers.find((item) => quantityInTier(quantity, item)) ?? tiers[0] ?? null

  if (tier === null) {
    return null
  }

  return {
    quantity,
    unitPriceTzs: tier.unitPriceTzs,
    totalTzs: quantity * tier.unitPriceTzs,
    tier,
  }
}
