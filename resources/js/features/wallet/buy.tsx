import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  createPayment,
  createQuote,
  fetchPayments,
  fetchWallet,
  paymentsQueryKey,
  quantityInTier,
  quoteQueryKey,
  walletQueryKey,
} from '@/lib/credits'
import { formatDateTime, formatNumber, formatTZS } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { type PricingTier } from '@/types/credits'

type BuyStep = 'quantity' | 'pay'

const LIPA_NAMBA_NAME = 'SOSCOM TECHNOLOGIES COMPANY LIMITED'

export function BuySmsPage() {
  const queryClient = useQueryClient()
  const profilePhone = useAuthStore((state) => state.user?.customer?.phone ?? '')
  const [step, setStep] = useState<BuyStep>('quantity')
  const [quantityInput, setQuantityInput] = useState('')
  const [payerReference, setPayerReference] = useState(profilePhone)
  const walletQuery = useQuery({
    queryKey: walletQueryKey,
    queryFn: fetchWallet,
  })
  const paymentsQuery = useQuery({
    queryKey: paymentsQueryKey,
    queryFn: () => fetchPayments(1, 100),
  })
  const tiers = walletQuery.data?.tiers ?? []
  const minPurchaseQuantity = walletQuery.data?.minPurchaseQuantity ?? 50
  const lipaNamba =
    walletQuery.data?.lipaNamba ?? paymentsQuery.data?.lipaNamba ?? '123456'
  const lipaNambaName =
    walletQuery.data?.lipaNambaName ??
    paymentsQuery.data?.lipaNambaName ??
    LIPA_NAMBA_NAME
  const quantity = parsedSmsQuantity(quantityInput)
  const localQuote = useMemo(
    () => quoteFromTiers(quantity, tiers, minPurchaseQuantity),
    [minPurchaseQuantity, quantity, tiers]
  )
  const quoteQuery = useQuery({
    queryKey: quoteQueryKey(quantity),
    queryFn: () => createQuote(quantity),
    enabled: quantity >= minPurchaseQuantity,
    retry: false,
  })
  const quote =
    quoteQuery.data?.quantity === quantity ? quoteQuery.data : localQuote
  const belowMinimum = quantity > 0 && quantity < minPurchaseQuantity
  const pending = (paymentsQuery.data?.payments ?? []).filter(
    (item) => item.status === 'pending'
  )

  const buyMutation = useMutation({
    mutationFn: () =>
      createPayment({
        quantity,
        payer_reference: payerReference.trim(),
      }),
    onSuccess: async () => {
      toast.success(
        'We have recorded your payment. SMS will be added after Soscom confirms it.'
      )
      setStep('quantity')
      setQuantityInput('')
      setPayerReference(profilePhone)
      await queryClient.invalidateQueries({ queryKey: walletQueryKey })
      await queryClient.invalidateQueries({ queryKey: paymentsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function selectTier(tier: PricingTier) {
    setQuantityInput(String(tier.minQuantity))
    setStep('quantity')
  }

  function goToPay() {
    if (quantity < minPurchaseQuantity) {
      toast.error(`Enter at least ${formatNumber(minPurchaseQuantity)} SMS.`)
      return
    }
    setStep('pay')
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Buy SMS'
          description=''
        />
        {walletQuery.isError ? (
          <p className='text-sm text-destructive'>
            Could not load SMS prices. Refresh and try again.
          </p>
        ) : (
          <div className='w-full space-y-6'>
            {tiers.length > 0 ? (
              <div className='grid w-full gap-4 sm:grid-cols-2 xl:grid-cols-4'>
                {tiers.map((tier) => (
                  <button
                    key={tier.id}
                    type='button'
                    onClick={() => selectTier(tier)}
                    className={cn(
                      'rounded-xl border bg-card p-5 text-start',
                      quantityInTier(quantity, tier) && 'ring-2 ring-primary'
                    )}
                  >
                    <p className='text-2xl font-semibold'>{tier.label}</p>
                    <p className='mt-1 text-muted-foreground'>
                      {formatTZS(tier.unitPriceTzs)} per SMS
                    </p>
                  </button>
                ))}
              </div>
            ) : null}

            <div className='w-full rounded-xl border bg-card p-5 sm:p-6'>
              <ol className='mb-6 grid grid-cols-2 gap-2 text-sm'>
                <li
                  className={cn(
                    'rounded-lg border px-3 py-2',
                    step === 'quantity'
                      ? 'border-primary bg-primary/5 font-medium'
                      : 'text-muted-foreground'
                  )}
                >
                  1. How many SMS
                </li>
                <li
                  className={cn(
                    'rounded-lg border px-3 py-2',
                    step === 'pay'
                      ? 'border-primary bg-primary/5 font-medium'
                      : 'text-muted-foreground'
                  )}
                >
                  2. Pay
                </li>
              </ol>

              {step === 'quantity' ? (
                <div className='space-y-5'>
                  <div className='space-y-2'>
                    <Label htmlFor='sms-quantity'>How many SMS do you want?</Label>
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
                  <QuoteSummary
                    quote={quote}
                    emptyHint={
                      quantity === 0
                        ? ''
                        : belowMinimum
                          ? null
                          : 'Calculating the amount…'
                    }
                  />
                  <Button
                    className='w-full sm:w-auto'
                    disabled={quantity < minPurchaseQuantity || !quote}
                    onClick={goToPay}
                  >
                    Next
                  </Button>
                </div>
              ) : (
                <div className='space-y-5'>
                  {quoteQuery.isError && !quote ? (
                    <p className='text-sm text-destructive'>
                      Could not load the amount to pay. Go back and try again.
                    </p>
                  ) : (
                    <div className='grid gap-5 lg:grid-cols-2'>
                      <QuoteSummary quote={quote} emptyHint='Preparing your amount…' />
                      <div className='rounded-lg border bg-muted/30 p-4'>
                        <p className='text-sm text-muted-foreground'>Lipa namba</p>
                        <p className='mt-1 font-mono text-2xl font-semibold tracking-wide'>
                          {lipaNamba}
                        </p>
                        <p className='mt-3 text-sm text-muted-foreground'>Name</p>
                        <p className='mt-1 font-medium'>{lipaNambaName}</p>
                        <p className='mt-3 text-sm text-muted-foreground'>
                          Pay this amount to that Lipa namba. SMS is added after
                          Soscom confirms the payment.
                        </p>
                      </div>
                    </div>
                  )}
                  <div className='max-w-md space-y-2'>
                    <Label htmlFor='payer-reference'>
                      Mobile number you will pay with
                    </Label>
                    <Input
                      id='payer-reference'
                      value={payerReference}
                      onChange={(event) => setPayerReference(event.target.value)}
                    />
                  </div>
                  <div className='flex gap-2'>
                    <Button
                      type='button'
                      variant='outline'
                      onClick={() => setStep('quantity')}
                    >
                      Back
                    </Button>
                    <Button
                      disabled={
                        buyMutation.isPending ||
                        !quote ||
                        payerReference.trim().length < 3
                      }
                      onClick={() => buyMutation.mutate()}
                    >
                      {buyMutation.isPending ? 'Please wait…' : 'Pay'}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
        {pending.length > 0 ? (
          <div className='mt-10 w-full'>
            <h2 className='mb-3 text-lg font-semibold'>Waiting for confirmation</h2>
            <TableWrap>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Requested</TableHead>
                    <TableHead>SMS</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Payer</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {pending.map((item) => (
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
          </div>
        ) : null}
      </Main>
    </>
  )
}

type QuotePreview = {
  quantity: number
  unitPriceTzs: number
  totalTzs: number
  tier: PricingTier
}

function QuoteSummary({
  quote,
  emptyHint,
}: {
  quote: QuotePreview | null
  emptyHint: string | null
}) {
  if (!quote) {
    return emptyHint ? (
      <p className='text-sm text-muted-foreground'>{emptyHint}</p>
    ) : null
  }

  return (
    <div className='rounded-lg border bg-muted/30 p-4'>
      <p className='text-sm text-muted-foreground'>Amount to pay</p>
      <p className='mt-1 text-3xl font-semibold tracking-tight'>
        {formatTZS(quote.totalTzs)}
      </p>
      <p className='mt-2 text-sm text-muted-foreground'>
        {formatNumber(quote.quantity)} SMS
        {quantityInTier(quote.quantity, quote.tier) ? ` · ${quote.tier.label}` : ''}{' '}
        at {formatTZS(quote.unitPriceTzs)} each
      </p>
    </div>
  )
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
