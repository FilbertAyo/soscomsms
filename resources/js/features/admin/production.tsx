import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  adminLogsQueryKey,
  adminProductionQueryKey,
  confirmAdminProviderAccount,
  confirmAdminProviderWhitelist,
  fetchAdminProduction,
  runAdminDatabaseBackup,
  updateAdminProduction,
} from '@/lib/admin'
import { adminProviderBalanceQueryKey } from '@/lib/credits'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { AdminPage } from './page'

export function AdminProduction() {
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: adminProductionQueryKey,
    queryFn: fetchAdminProduction,
  })
  const snapshot = query.data
  const [staticIp, setStaticIp] = useState('')
  const [baseUrl, setBaseUrl] = useState('')
  const [countryCode, setCountryCode] = useState('ALL')
  const [threshold, setThreshold] = useState('10000')

  useEffect(() => {
    if (!snapshot) {
      return
    }
    setStaticIp(snapshot.provider.staticIp ?? '')
    setBaseUrl(snapshot.provider.baseUrl ?? '')
    setCountryCode(snapshot.provider.countryCode || 'ALL')
    setThreshold(String(snapshot.provider.lowBalanceThreshold))
  }, [snapshot])

  async function refreshRelated() {
    await queryClient.invalidateQueries({ queryKey: adminProductionQueryKey })
    await queryClient.invalidateQueries({
      queryKey: adminProviderBalanceQueryKey,
    })
    await queryClient.invalidateQueries({ queryKey: adminLogsQueryKey })
  }

  const saveMutation = useMutation({
    mutationFn: () =>
      updateAdminProduction({
        static_ip: staticIp.trim() || undefined,
        base_url: baseUrl.trim() || undefined,
        country_code: countryCode.trim() || undefined,
        low_balance_threshold: Number(threshold),
      }),
    onSuccess: async () => {
      toast.success('Provider production settings saved.')
      await refreshRelated()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const whitelistMutation = useMutation({
    mutationFn: confirmAdminProviderWhitelist,
    onSuccess: async () => {
      toast.success('Marked the SOSCOM static IP as whitelisted by Mobishastra.')
      await refreshRelated()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const accountMutation = useMutation({
    mutationFn: confirmAdminProviderAccount,
    onSuccess: async () => {
      toast.success('Tanzania Mobishastra account confirmed.')
      await refreshRelated()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const backupMutation = useMutation({
    mutationFn: runAdminDatabaseBackup,
    onSuccess: async (result) => {
      toast.success(result.message || 'Database backup finished.')
      await refreshRelated()
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  return (
    <AdminPage
      title='Production readiness'
      description='Record the SOSCOM static IP, confirm Mobishastra has whitelisted it, and keep local/staging on the fake gateway until that gate is done.'
    >
      {query.isError ? (
        <p className='text-sm text-destructive'>
          Could not load production readiness.
        </p>
      ) : (
        <div className='space-y-6'>
          <div className='rounded-xl border bg-card p-5'>
            <p className='text-sm text-muted-foreground'>
              Environment: {snapshot?.environment ?? '…'} · Gateway:{' '}
              {snapshot?.driver ?? '…'} · APP_URL: {snapshot?.appUrl ?? '…'}
            </p>
            {snapshot?.driver === 'fake' ? (
              <p className='mt-3 text-sm text-muted-foreground'>
                Local and staging stay on the fake gateway. Set
                SMS_GATEWAY=mobishastra in production only after this checklist
                is green.
              </p>
            ) : snapshot?.blockReason ? (
              <p className='mt-3 text-sm text-destructive'>
                {snapshot.blockReason}
              </p>
            ) : (
              <p className='mt-3 text-sm text-primary'>
                Mobishastra sending is allowed from this server.
              </p>
            )}
            {snapshot?.driver === 'fake' && snapshot?.blockReason ? (
              <p className='mt-2 text-sm text-muted-foreground'>
                Go-live remaining: {snapshot.blockReason}
              </p>
            ) : null}
            <p className='mt-3 text-sm text-muted-foreground'>
              Queue: {snapshot?.queueConnection ?? '…'} ·{' '}
              {snapshot?.queueHeartbeatAt
                ? `heartbeat ${snapshot.queueHeartbeatAt}`
                : 'no heartbeat'}{' '}
              · {snapshot?.queuePendingJobs ?? 0} pending ·{' '}
              {snapshot?.queueFailedJobs ?? 0} failed
            </p>
          </div>

          <section className='space-y-2'>
            {(snapshot?.checks ?? []).map((check) => (
              <div
                key={check.id}
                className='flex items-start justify-between gap-4 rounded-xl border bg-card px-4 py-3'
              >
                <div>
                  <p className='font-medium'>{check.label}</p>
                  <p className='text-sm text-muted-foreground'>{check.detail}</p>
                </div>
                <p
                  className={
                    check.ready
                      ? 'text-sm font-medium text-primary'
                      : 'text-sm font-medium text-destructive'
                  }
                >
                  {check.ready ? 'Ready' : 'Needed'}
                </p>
              </div>
            ))}
          </section>

          <form
            className='max-w-xl space-y-4 rounded-xl border bg-card p-5'
            onSubmit={(event) => {
              event.preventDefault()
              saveMutation.mutate()
            }}
          >
            <div className='space-y-2'>
              <Label htmlFor='static-ip'>SOSCOM static IP</Label>
              <Input
                id='static-ip'
                value={staticIp}
                onChange={(event) => setStaticIp(event.target.value)}
                placeholder='203.0.113.10'
              />
              <p className='text-xs text-muted-foreground'>
                Send this IP to Mobishastra for whitelist. Customers never need
                their own IP whitelisted.
              </p>
            </div>
            <div className='space-y-2'>
              <Label htmlFor='base-url'>Tanzania base URL</Label>
              <Input
                id='base-url'
                value={baseUrl}
                onChange={(event) => setBaseUrl(event.target.value)}
                placeholder='https://mshastra.com'
              />
            </div>
            <div className='grid gap-4 sm:grid-cols-2'>
              <div className='space-y-2'>
                <Label htmlFor='country-code'>CountryCode</Label>
                <Input
                  id='country-code'
                  value={countryCode}
                  onChange={(event) =>
                    setCountryCode(event.target.value.toUpperCase())
                  }
                />
              </div>
              <div className='space-y-2'>
                <Label htmlFor='low-balance'>Low-balance alert (SMS)</Label>
                <Input
                  id='low-balance'
                  type='number'
                  min={0}
                  value={threshold}
                  onChange={(event) => setThreshold(event.target.value)}
                />
              </div>
            </div>
            <Button type='submit' disabled={saveMutation.isPending}>
              {saveMutation.isPending ? 'Saving…' : 'Save settings'}
            </Button>
          </form>

          <div className='flex flex-col gap-3 sm:flex-row'>
            <Button
              variant='outline'
              disabled={whitelistMutation.isPending}
              onClick={() => whitelistMutation.mutate()}
            >
              Confirm IP is whitelisted
            </Button>
            <Button
              variant='outline'
              disabled={accountMutation.isPending}
              onClick={() => accountMutation.mutate()}
            >
              Confirm Tanzania account
            </Button>
            <Button
              variant='outline'
              disabled={backupMutation.isPending}
              onClick={() => backupMutation.mutate()}
            >
              Run database backup
            </Button>
          </div>
          <p className='text-sm text-muted-foreground'>
            Last backup:{' '}
            {snapshot?.lastBackupAt
              ? new Date(snapshot.lastBackupAt).toLocaleString('en-TZ')
              : 'None yet'}
            . Kept for {snapshot?.backupKeepDays ?? 14} days. Username:{' '}
            {snapshot?.provider.profileId || 'Not set'}. Password:{' '}
            {snapshot?.provider.passwordSet ? 'Set' : 'Missing'}.
          </p>
        </div>
      )}
    </AdminPage>
  )
}
