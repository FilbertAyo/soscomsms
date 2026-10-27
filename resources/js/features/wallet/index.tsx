import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { Receipt } from 'lucide-react'
import { fetchWallet, walletQueryKey, withSmsBalance } from '@/lib/credits'
import { formatNumber } from '@/lib/format'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { AppHeader } from '@/components/soscom/app-header'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { ListSkeleton } from '@/components/soscom/skeletons'
import { StatusBadge } from '@/components/soscom/status-badge'
import { Main } from '@/components/layout/main'

export function WalletPage() {
  const walletQuery = useQuery({
    queryKey: walletQueryKey,
    queryFn: fetchWallet,
  })
  const wallet = walletQuery.data

  useEffect(() => {
    if (!wallet) {
      return
    }

    const current = useAuthStore.getState().user
    if (!current?.customer || current.customer.smsBalance === wallet.smsBalance) {
      return
    }

    useAuthStore.getState().setUser(withSmsBalance(current, wallet.smsBalance))
  }, [wallet])

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='SMS Balance'
          description='SMS credits are used each time you send a message. New accounts start with 20 free SMS.'
        />
        {walletQuery.isError ? (
          <QueryLoadError
            message="We couldn't load your wallet."
            onRetry={() => void walletQuery.refetch()}
          />
        ) : (
          <>
            <div className='mb-8 rounded-xl border bg-card p-6'>
              <p className='text-sm text-muted-foreground'>Available SMS</p>
              {walletQuery.isLoading ? (
                <Skeleton className='mt-2 h-10 w-40' />
              ) : (
                <p className='mt-2 text-4xl font-semibold'>
                  {formatNumber(wallet?.smsBalance ?? 0)} SMS
                </p>
              )}
              <div className='mt-6 flex flex-wrap gap-2'>
                <Button asChild>
                  <Link to='/wallet/buy'>Buy SMS</Link>
                </Button>
                <Button variant='outline' asChild>
                  <Link to='/wallet/transactions'>View Transactions</Link>
                </Button>
              </div>
            </div>
            <div>
              <h2 className='mb-3 text-lg font-semibold'>Latest purchases</h2>
              {walletQuery.isLoading ? (
                <ListSkeleton rows={3} />
              ) : wallet && wallet.recentTransactions.length === 0 ? (
                <EmptyState
                  icon={Receipt}
                  title='No purchases yet'
                  description='Your signup bonus and SMS purchases will show up here.'
                />
              ) : (
                <div className='space-y-2'>
                  {(wallet?.recentTransactions ?? []).map((item) => (
                    <div
                      key={item.id}
                      className='flex items-center justify-between rounded-xl border bg-card px-4 py-3'
                    >
                      <div>
                        <p className='font-medium'>{item.description}</p>
                        <p className='text-xs text-muted-foreground'>
                          {item.quantity > 0 ? '+' : ''}
                          {formatNumber(item.quantity)} SMS
                        </p>
                      </div>
                      <StatusBadge status={item.type} />
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </Main>
    </>
  )
}
