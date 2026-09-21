import { useQuery } from '@tanstack/react-query'
import { formatNumber } from '@/lib/format'
import {
  adminProviderBalanceQueryKey,
  fetchAdminProviderBalance,
} from '@/lib/credits'
import { AdminPage } from './page'

export function AdminProviderBalance() {
  const balanceQuery = useQuery({
    queryKey: adminProviderBalanceQueryKey,
    queryFn: fetchAdminProviderBalance,
  })
  const snapshot = balanceQuery.data

  return (
    <AdminPage
      title='SMS Provider Balance'
      description='Mobishastra remaining, unused customer credits, and the real leftover after bonuses and buys. Customers never see this.'
    >
      {balanceQuery.isError ? (
        <p className='text-sm text-destructive'>
          Could not load the provider balance.
        </p>
      ) : (
        <div className='max-w-3xl space-y-4'>
          <div className='rounded-xl border bg-card p-6'>
            <p className='text-sm text-muted-foreground'>
              {snapshot?.name ?? 'Provider'} ({snapshot?.driver ?? '…'})
            </p>
            <p className='mt-2 text-4xl font-semibold'>
              {snapshot?.parsed === false
                ? '—'
                : `${formatNumber(snapshot?.smsBalance ?? 0)} SMS`}
            </p>
            {snapshot?.parsed === false ? (
              <p className='mt-4 text-sm text-destructive'>
                {snapshot.parseError ||
                  'Mobishastra did not return a numeric SMS balance.'}{' '}
                Confirm the 8-digit profile ID and password, then compare the
                raw reply below with the Mobishastra portal.
              </p>
            ) : snapshot?.lowBalance ? (
              <p className='mt-4 text-sm text-destructive'>
                Balance is below the warning threshold of{' '}
                {formatNumber(snapshot.lowBalanceThreshold)} SMS. Top up the
                upstream account before customer traffic is affected.
              </p>
            ) : (
              <p className='mt-4 text-sm text-muted-foreground'>
                Warning threshold:{' '}
                {formatNumber(snapshot?.lowBalanceThreshold ?? 0)} SMS.
              </p>
            )}
            {snapshot?.driver === 'mobishastra' && snapshot.rawResponse ? (
              <p className='mt-3 break-all font-mono text-xs text-muted-foreground'>
                Mobishastra returned: {snapshot.rawResponse}
              </p>
            ) : null}
          </div>
          <div className='grid gap-4 sm:grid-cols-2'>
            <BalanceCard
              label='Customer credits'
              value={`${formatNumber(snapshot?.customerCredits ?? 0)} SMS`}
              hint='Unused signup bonuses and purchases still sitting in customer wallets. Mobishastra does not drop when these credits are granted.'
            />
            <BalanceCard
              label='Real remaining'
              value={
                snapshot?.parsed === false
                  ? '—'
                  : `${formatNumber(snapshot?.realSmsBalance ?? 0)} SMS`
              }
              hint='Provider remaining minus customer credits. This is what is left after bonuses and buys.'
              warn={(snapshot?.realSmsBalance ?? 0) < 0}
            />
          </div>
          <div className='rounded-xl border bg-card p-5 text-sm text-muted-foreground'>
            <p>Base URL: {snapshot?.baseUrl || 'Not set'}</p>
            <p className='mt-1'>Username: {snapshot?.profileId || 'Not set'}</p>
            <p className='mt-1'>
              Static IP (for whitelist): {snapshot?.staticIp || 'Not recorded'}
            </p>
            <p className='mt-1'>
              Whitelisted:{' '}
              {snapshot?.staticIpWhitelistedAt
                ? new Date(snapshot.staticIpWhitelistedAt).toLocaleString('en-TZ')
                : 'Not confirmed'}
            </p>
            <p className='mt-1'>CountryCode: {snapshot?.countryCode || 'ALL'}</p>
            <p className='mt-1'>
              Tanzania account:{' '}
              {snapshot?.credentialsConfirmedAt
                ? new Date(snapshot.credentialsConfirmedAt).toLocaleString(
                    'en-TZ'
                  )
                : 'Not confirmed'}
            </p>
          </div>
        </div>
      )}
    </AdminPage>
  )
}

function BalanceCard({
  label,
  value,
  hint,
  warn = false,
}: {
  label: string
  value: string
  hint: string
  warn?: boolean
}) {
  return (
    <div className='rounded-xl border bg-card p-5'>
      <p className='text-sm text-muted-foreground'>{label}</p>
      <p className={`mt-2 text-2xl font-semibold ${warn ? 'text-destructive' : ''}`}>
        {value}
      </p>
      <p className='mt-3 text-xs leading-5 text-muted-foreground'>{hint}</p>
    </div>
  )
}
