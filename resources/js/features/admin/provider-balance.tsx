import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import { formatNumber } from '@/lib/format'
import {
  adminProviderBalanceQueryKey,
  checkAdminProviderDlr,
  fetchAdminProviderBalance,
  type ProviderDlrCheck,
} from '@/lib/credits'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { AdminPage } from './page'

export function AdminProviderBalance() {
  const [messageId, setMessageId] = useState('1871080898')
  const [dlrResult, setDlrResult] = useState<ProviderDlrCheck | null>(null)
  const balanceQuery = useQuery({
    queryKey: adminProviderBalanceQueryKey,
    queryFn: fetchAdminProviderBalance,
  })
  const dlrCheck = useMutation({
    mutationFn: () => checkAdminProviderDlr(messageId.trim()),
    onSuccess: (result) => {
      setDlrResult(result)
    },
    onError: (error) => {
      setDlrResult(null)
      toast.error(applyValidationErrors(error, () => undefined))
    },
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
          <form
            className='rounded-xl border bg-card p-5'
            onSubmit={(event) => {
              event.preventDefault()
              if (!messageId.trim()) {
                toast.error('Enter a Mobishastra message ID.')
                return
              }
              dlrCheck.mutate()
            }}
          >
            <h2 className='font-medium'>Test DLR status</h2>
            <p className='mt-1 text-sm text-muted-foreground'>
              Calls dlrstatus_api from this server with the saved profile and
              password. The reply is shown here and is not saved onto a campaign.
            </p>
            <div className='mt-4 flex flex-col gap-3 sm:flex-row sm:items-end'>
              <div className='flex-1 space-y-2'>
                <Label htmlFor='dlr-message-id'>Message ID</Label>
                <Input
                  id='dlr-message-id'
                  value={messageId}
                  onChange={(event) => setMessageId(event.target.value)}
                  placeholder='1871080898'
                />
              </div>
              <Button type='submit' disabled={dlrCheck.isPending}>
                {dlrCheck.isPending ? 'Checking…' : 'Check DLR'}
              </Button>
            </div>
            {dlrResult ? (
              <dl className='mt-4 space-y-2 text-sm'>
                <div>
                  <dt className='text-muted-foreground'>Request</dt>
                  <dd className='break-all font-mono text-xs'>
                    {dlrResult.requestUrl || dlrResult.driver}
                    ?messageid={dlrResult.messageId}
                  </dd>
                </div>
                <div>
                  <dt className='text-muted-foreground'>Raw response</dt>
                  <dd className='break-all font-mono text-xs'>
                    {dlrResult.rawResponse || 'Empty response'}
                  </dd>
                </div>
                <div>
                  <dt className='text-muted-foreground'>Parsed DLR status</dt>
                  <dd>{dlrResult.dlrStatus || '—'}</dd>
                </div>
                <div>
                  <dt className='text-muted-foreground'>DLR time</dt>
                  <dd>{dlrResult.dlrTime || '—'}</dd>
                </div>
                <div>
                  <dt className='text-muted-foreground'>Soscom status</dt>
                  <dd>{dlrResult.soscomStatus || 'Not mapped'}</dd>
                </div>
              </dl>
            ) : null}
          </form>
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
