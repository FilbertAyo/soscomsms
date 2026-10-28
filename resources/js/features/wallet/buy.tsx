import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Clock, Smartphone, X } from 'lucide-react'
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
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { Main } from '@/components/layout/main'
import { type PricingTier } from '@/types/credits'
import { type PaymentRecord } from '@/types/payments'

export function BuySmsPage() {
  const queryClient = useQueryClient()
  const profilePhone = useAuthStore((state) => state.user?.customer?.phone ?? '')
  const [quantityInput, setQuantityInput] = useState('')
  const [payerDigits, setPayerDigits] = useState(() => tzLocalDigits(profilePhone))
  const payerValid = /^[67]\d{8}$/.test(payerDigits)
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
  const minPurchaseQuantity = walletQuery.data?.minPurchaseQuantity ?? 50
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
        payer_reference: `+255${payerDigits}`,
      }),
    onSuccess: async (result) => {
      queryClient.setQueryData(['payments', 'status', result.payment.id], {
        payment: result.payment,
        smsBalance: null,
      })
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
                Pay with M-Pesa, Airtel Money, Mixx by Yas, or Halotel. 
              </p>
            </div>

            {walletQuery.isError ? (
              <QueryLoadError
                message="We couldn't load SMS prices."
                onRetry={() => void walletQuery.refetch()}
              />
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
                          {tier.specialPrice ? 'Your price ' : ''}
                          {formatTZS(tier.unitPriceTzs)} each
                        </p>
                        {tier.specialPrice && tier.listUnitPriceTzs != null ? (
                          <p className='text-xs text-muted-foreground line-through'>
                            {formatTZS(tier.listUnitPriceTzs)}
                          </p>
                        ) : null}
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
                  {quote?.specialPrice ? (
                    <p className='mt-1 text-sm text-muted-foreground'>
                      Your price: {formatNumber(quote.quantity)} SMS at{' '}
                      {formatTZS(quote.unitPriceTzs)} each. Normal price is{' '}
                      {formatTZS(quote.listUnitPriceTzs ?? quote.unitPriceTzs)} each.
                    </p>
                  ) : quote ? (
                    <p className='mt-1 text-sm text-muted-foreground'>
                      {formatNumber(quote.quantity)} SMS at {formatTZS(quote.unitPriceTzs)} each
                    </p>
                  ) : null}
                </div>

                <div className='space-y-2'>
                  <Label htmlFor='payer-reference'>Mobile number to approve the payment</Label>
                  <div className='relative'>
                    <span className='pointer-events-none absolute inset-y-0 start-0 flex items-center border-e px-3 text-sm text-muted-foreground select-none'>
                      +255
                    </span>
                    <Input
                      id='payer-reference'
                      type='tel'
                      inputMode='numeric'
                      autoComplete='tel-national'
                      placeholder='7XXXXXXXX'
                      className='ps-16'
                      value={payerDigits}
                      onChange={(event) =>
                        setPayerDigits(tzLocalDigits(event.target.value))
                      }
                    />
                  </div>
                  {payerDigits.length === 9 && !payerValid ? (
                    <p className='text-sm text-destructive'>
                      Enter a Tanzanian mobile number starting with 6 or 7.
                    </p>
                  ) : (
                    <p className='text-xs text-muted-foreground'>
                      This number receives the PIN prompt. It can be different
                      from the number on your account.
                    </p>
                  )}
                </div>

                <Button
                  className='w-full'
                  disabled={
                    buyMutation.isPending ||
                    quantity < minPurchaseQuantity ||
                    !quote ||
                    !payerValid
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
    <div className='flex flex-col items-center py-4 text-center' aria-live='polite'>
      <StatusMark tone='waiting'>
        <Smartphone className='size-9 text-primary' aria-hidden='true' />
      </StatusMark>
      <p className='mt-6 text-lg font-semibold tracking-tight'>
        Waiting for you to approve on your phone
      </p>
      <p className='mt-1 max-w-md text-sm text-muted-foreground'>
        A prompt was sent to {payment.payerReference}. Open it and enter your
        mobile-money PIN. This page updates on its own.
      </p>
      <AmountPanel
        amount={formatTZS(payment.amountTzs)}
        detail={
          payment.specialPrice && payment.listUnitPriceTzs != null
            ? `${formatNumber(payment.quantity)} SMS at your price of ${formatTZS(payment.unitPriceTzs)} each (normal ${formatTZS(payment.listUnitPriceTzs)})`
            : `${formatNumber(payment.quantity)} SMS at ${formatTZS(payment.unitPriceTzs)} each`
        }
      />
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
  const expired = payment.status === 'expired'
  const title = paid
    ? 'Payment confirmed'
    : expired
      ? 'This prompt expired'
      : 'Payment was not completed'
  const detail = paid
    ? `${formatNumber(payment.quantity)} SMS have been added to your account.`
    : expired
      ? 'The mobile-money prompt timed out. No SMS were added. Start a new purchase when you are ready.'
      : payment.rejectionReason ??
        'The prompt was not approved. No SMS were added, and you have not been charged.'

  return (
    <div className='flex flex-col items-center py-4 text-center' aria-live='polite'>
      <StatusMark tone={paid ? 'paid' : expired ? 'expired' : 'failed'}>
        {paid ? (
          <Check className='size-10' strokeWidth={2.5} aria-hidden='true' />
        ) : expired ? (
          <Clock className='size-9' aria-hidden='true' />
        ) : (
          <X className='size-10' strokeWidth={2.5} aria-hidden='true' />
        )}
      </StatusMark>
      <p className='mt-6 text-lg font-semibold tracking-tight'>{title}</p>
      <p className='mt-1 max-w-md text-sm text-muted-foreground'>{detail}</p>
      <AmountPanel
        amount={paid ? `${formatNumber(payment.quantity)} SMS` : formatTZS(payment.amountTzs)}
        detail={
          paid
            ? formatTZS(payment.amountTzs)
            : `${formatNumber(payment.quantity)} SMS · ${payment.payerReference}`
        }
      />
      <Button
        className='mt-6 w-full'
        variant={paid ? 'default' : 'outline'}
        onClick={onAgain}
      >
        {paid ? 'Buy more SMS' : 'Try again'}
      </Button>
    </div>
  )
}

function StatusMark({
  tone,
  children,
}: {
  tone: 'waiting' | 'paid' | 'failed' | 'expired'
  children: ReactNode
}) {
  const colors = {
    waiting: {
      ping: 'bg-primary/10',
      ring: 'border-muted',
      icon: 'text-primary',
    },
    paid: {
      ping: 'bg-primary/15',
      ring: 'border-primary/25',
      icon: 'text-primary',
    },
    failed: {
      ping: 'bg-destructive/10',
      ring: 'border-destructive/25',
      icon: 'text-destructive',
    },
    expired: {
      ping: 'bg-muted',
      ring: 'border-muted-foreground/20',
      icon: 'text-muted-foreground',
    },
  }[tone]

  return (
    <div className={cn('relative flex size-28 items-center justify-center', colors.icon)}>
      {tone === 'waiting' ? (
        <span className={cn('absolute inset-0 animate-ping rounded-full [animation-duration:2s]', colors.ping)} />
      ) : (
        <span className={cn('absolute inset-2 rounded-full', colors.ping)} />
      )}
      <span className={cn('absolute inset-0 rounded-full border-4', colors.ring)} />
      {tone === 'waiting' ? (
        <span className='absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-primary [animation-duration:1.2s]' />
      ) : null}
      <span className='relative z-10'>{children}</span>
    </div>
  )
}

function AmountPanel({ amount, detail }: { amount: string; detail: string }) {
  return (
    <div className='mt-6 w-full rounded-xl bg-muted/40 px-4 py-4'>
      <p className='text-3xl font-semibold tracking-tight'>{amount}</p>
      <p className='mt-1 text-sm text-muted-foreground'>{detail}</p>
    </div>
  )
}

type QuotePreview = {
  quantity: number
  unitPriceTzs: number
  listUnitPriceTzs: number
  specialPrice: boolean
  totalTzs: number
  tier: PricingTier
}

function tzLocalDigits(value: string): string {
  let digits = value.replace(/\D+/g, '')

  if (digits.startsWith('255') && digits.length > 9) {
    digits = digits.slice(3)
  }

  return digits.replace(/^0+/, '').slice(0, 9)
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

  const listUnitPriceTzs = tier.listUnitPriceTzs ?? tier.unitPriceTzs

  return {
    quantity,
    unitPriceTzs: tier.unitPriceTzs,
    listUnitPriceTzs,
    specialPrice: tier.specialPrice === true && tier.unitPriceTzs !== listUnitPriceTzs,
    totalTzs: quantity * tier.unitPriceTzs,
    tier,
  }
}
