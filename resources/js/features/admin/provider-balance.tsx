import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import { formatDateTime, formatNumber } from '@/lib/format'
import {
  adminProviderBalanceQueryKey,
  checkAdminProviderDlr,
  fetchAdminProviderBalance,
  runAdminDlrPoll,
  type ProviderDlrCheck,
} from '@/lib/credits'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { type ProviderBalance, type SchedulerHealth } from '@/types/credits'
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
        <div className='space-y-4'>
          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-3'>
            <div className='rounded-xl border bg-card p-5'>
              <p className='text-sm text-muted-foreground'>
                {snapshot?.name ?? 'Provider'} ({snapshot?.driver ?? '…'})
              </p>
              <p
                className={`mt-2 text-2xl font-semibold ${snapshot?.parsed === false || snapshot?.lowBalance ? 'text-destructive' : ''}`}
              >
                {snapshot?.parsed === false
                  ? '—'
                  : `${formatNumber(snapshot?.smsBalance ?? 0)} SMS`}
              </p>
              {snapshot?.parsed === false ? (
                <p className='mt-3 text-xs leading-5 text-destructive'>
                  {snapshot.parseError ||
                    'Mobishastra did not return a numeric SMS balance.'}{' '}
                  Confirm the 8-digit profile ID and password, then compare the
                  raw reply below with the Mobishastra portal.
                </p>
              ) : snapshot?.lowBalance ? (
                <p className='mt-3 text-xs leading-5 text-destructive'>
                  Balance is below the warning threshold of{' '}
                  {formatNumber(snapshot.lowBalanceThreshold)} SMS. Top up the
                  upstream account before customer traffic is affected.
                </p>
              ) : (
                <p className='mt-3 text-xs leading-5 text-muted-foreground'>
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
                ? new Date(snapshot.staticIpWhitelistedAt).toLocaleString(
                    'en-TZ'
                  )
                : 'Not confirmed'}
            </p>
            <p className='mt-1'>
              CountryCode: {snapshot?.countryCode || 'ALL'}
            </p>
            <p className='mt-1'>
              Tanzania account:{' '}
              {snapshot?.credentialsConfirmedAt
                ? new Date(snapshot.credentialsConfirmedAt).toLocaleString(
                    'en-TZ'
                  )
                : 'Not confirmed'}
            </p>
          </div>
          {snapshot?.driver === 'mobishastra' ? (
            <DlrPollStatus health={snapshot} />
          ) : null}
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
              password. If the message ID belongs to a Soscom SMS, the result is
              saved onto that delivery report.
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
                <div>
                  <dt className='text-muted-foreground'>Delivery report</dt>
                  <dd>
                    {!dlrResult.matchedDelivery
                      ? 'No Soscom SMS has this message ID.'
                      : `Now ${dlrResult.reportStatus ?? 'unknown'}${dlrResult.saved ? ' (updated)' : ''}`}
                  </dd>
                </div>
              </dl>
            ) : null}
          </form>
        </div>
      )}
    </AdminPage>
  )
}

function minutesSince(value: string | null | undefined): number | null {
  if (!value) return null
  return Math.floor((Date.now() - new Date(value).getTime()) / 60_000)
}

function ago(minutes: number | null): string {
  if (minutes === null) return 'never'
  if (minutes < 1) return 'just now'
  return `${minutes} min ago`
}

function DlrPollStatus({ health }: { health: SchedulerHealth }) {
  const queryClient = useQueryClient()
  const [output, setOutput] = useState<string | null>(null)
  const runNow = useMutation({
    mutationFn: runAdminDlrPoll,
    onSuccess: (result) => {
      setOutput(result.output)
      queryClient.setQueryData<ProviderBalance>(
        adminProviderBalanceQueryKey,
        (previous) => (previous ? { ...previous, ...result } : previous)
      )
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const poll = health.dlrPoll
  const pollMinutes = minutesSince(poll?.at)
  const startMinutes = minutesSince(health.dlrPollStartedAt)
  const cronMinutes = minutesSince(health.queueHeartbeatAt)
  const cronAlive = cronMinutes !== null && cronMinutes <= 3
  const pollAlive = pollMinutes !== null && pollMinutes <= 3
  const killed =
    !pollAlive &&
    startMinutes !== null &&
    (pollMinutes === null || startMinutes < pollMinutes)

  let problem: string | null = null
  if (!cronAlive) {
    problem =
      'The server cron is not running schedule:run, so nothing automatic happens (delivery checks, scheduled sends, queued jobs). Check the cPanel cron job.'
  } else if (killed) {
    problem =
      'The cron runs, but the delivery check starts and never finishes. The host is likely killing the process (CPU or time limit).'
  } else if (!pollAlive) {
    problem =
      'The cron runs, but the delivery check is not. Redeploy so the new schedule is loaded, or press Run now.'
  }

  return (
    <div className='rounded-xl border bg-card p-5'>
      <div className='flex items-start justify-between gap-3'>
        <div>
          <h2 className='font-medium'>Automatic delivery checks</h2>
          <p className='mt-1 text-xs text-muted-foreground'>
            Cron last seen {ago(cronMinutes)} · Delivery check last finished{' '}
            {ago(pollMinutes)}
            {poll ? ` (${formatDateTime(poll.at)})` : ''}
          </p>
        </div>
        <Button
          size='sm'
          variant='outline'
          disabled={runNow.isPending}
          onClick={() => runNow.mutate()}
        >
          {runNow.isPending ? 'Checking…' : 'Run now'}
        </Button>
      </div>
      {problem ? (
        <p className='mt-3 text-sm text-destructive'>{problem}</p>
      ) : (
        <p className='mt-3 text-sm text-muted-foreground'>
          Running every minute.
        </p>
      )}
      {poll ? (
        <p className='mt-2 text-sm text-muted-foreground'>
          Last run: checked {formatNumber(poll.checked)} · updated{' '}
          {formatNumber(poll.updated)} · still waiting{' '}
          {formatNumber(poll.waiting)}
          {poll.errors > 0 ? ` · errors ${formatNumber(poll.errors)}` : ''}
          {poll.seconds !== undefined ? ` · took ${poll.seconds}s` : ''}
        </p>
      ) : null}
      {output ? (
        <p className='mt-2 font-mono text-xs text-muted-foreground'>{output}</p>
      ) : null}
    </div>
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
      <p
        className={`mt-2 text-2xl font-semibold ${warn ? 'text-destructive' : ''}`}
      >
        {value}
      </p>
      <p className='mt-3 text-xs leading-5 text-muted-foreground'>{hint}</p>
    </div>
  )
}
