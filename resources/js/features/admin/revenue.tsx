import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  adminPricingTiersQueryKey,
  fetchAdminPricingTiers,
  updatePricingTier,
} from '@/lib/credits'
import { adminLogsQueryKey, adminOverviewQueryKey } from '@/lib/admin'
import { applyValidationErrors } from '@/lib/auth'
import { formatTZS } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { AdminPage } from './page'

export function AdminRevenue() {
  const queryClient = useQueryClient()
  const [drafts, setDrafts] = useState<Record<number, string>>({})
  const pricingQuery = useQuery({
    queryKey: adminPricingTiersQueryKey,
    queryFn: fetchAdminPricingTiers,
  })
  const saveMutation = useMutation({
    mutationFn: ({ id, unitPriceTzs }: { id: number; unitPriceTzs: number }) =>
      updatePricingTier(id, unitPriceTzs),
    onSuccess: async (tier) => {
      toast.success(`Updated ${tier.label} to ${formatTZS(tier.unitPriceTzs)} per SMS.`)
      void queryClient.invalidateQueries({ queryKey: adminPricingTiersQueryKey })
      void queryClient.invalidateQueries({ queryKey: adminOverviewQueryKey })
      void queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })
  const pricing = pricingQuery.data

  return (
    <AdminPage
      title='Pricing'
      description='Confirmed purchase and grant revenue, estimated send margin, and the normal volume prices. A special price for one customer is set on that customer’s page and does not change these prices.'
    >
      {pricingQuery.isError ? (
        <QueryLoadError
          message="We couldn't load pricing tiers."
          onRetry={() => void pricingQuery.refetch()}
        />
      ) : (
        <div className='space-y-4'>
          <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
            <Stat
              label='Purchase revenue'
              value={formatTZS(pricing?.purchaseRevenueTzs ?? 0)}
            />
            <Stat
              label='Billed usage'
              value={formatTZS(pricing?.billedUsageTzs ?? 0)}
            />
            <Stat
              label='Provider cost'
              value={formatTZS(pricing?.providerCostTzs ?? 0)}
            />
            <Stat
              label='Estimated margin'
              value={formatTZS(pricing?.estimatedMarginTzs ?? 0)}
            />
          </div>
          <p className='text-sm text-muted-foreground'>
            Provider cost used for margin estimates:{' '}
            {formatTZS(pricing?.providerUnitCostTzs ?? 14)} per SMS. Old ledger
            rows keep the price that was in effect at the time.
          </p>
          {(pricing?.tiers ?? []).map((tier) => {
            const value = drafts[tier.id] ?? String(tier.unitPriceTzs)
            return (
              <div
                key={tier.id}
                className='flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between'
              >
                <div>
                  <p className='font-medium'>{tier.label}</p>
                  <p className='text-sm text-muted-foreground'>
                    Current: {formatTZS(tier.unitPriceTzs)} per SMS
                  </p>
                </div>
                <div className='flex items-center gap-2'>
                  <Input
                    className='w-28'
                    type='number'
                    min={1}
                    value={value}
                    onChange={(event) =>
                      setDrafts((current) => ({
                        ...current,
                        [tier.id]: event.target.value,
                      }))
                    }
                  />
                  <Button
                    variant='outline'
                    disabled={
                      saveMutation.isPending ||
                      Number(value) < 1 ||
                      Number(value) === tier.unitPriceTzs
                    }
                    onClick={() =>
                      saveMutation.mutate({
                        id: tier.id,
                        unitPriceTzs: Number(value),
                      })
                    }
                  >
                    Save
                  </Button>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </AdminPage>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className='rounded-xl border bg-card p-5'>
      <p className='text-sm text-muted-foreground'>{label}</p>
      <p className='mt-2 text-2xl font-semibold'>{value}</p>
    </div>
  )
}
