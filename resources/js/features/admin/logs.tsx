import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { adminLogsPageQueryKey, fetchAdminLogs } from '@/lib/admin'
import { formatDateTime, formatTzMobile } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { ListPagination } from '@/components/soscom/list-pagination'
import { StatusBadge } from '@/components/soscom/status-badge'
import { AdminPage } from './page'
import { type AdminAuditLog, type AdminProviderLog } from '@/types/admin'

const badgeClass = 'px-1.5 py-0 text-[10px] leading-4'

export function AdminAuditLogs() {
  const [page, setPage] = useState(1)
  const logsQuery = useQuery({
    queryKey: adminLogsPageQueryKey(page, 1),
    queryFn: () => fetchAdminLogs(page, 1),
  })
  const logs = logsQuery.data?.auditLogs ?? []
  const meta = logsQuery.data?.auditMeta

  return (
    <AdminPage
      title='Audit Logs'
      description='Admin audit trail. Passwords are never shown.'
    >
      {logsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load audit logs.</p>
      ) : (
        <>
          {logs.length === 0 ? (
            <p className='text-xs text-muted-foreground'>
              {logsQuery.isLoading
                ? 'Loading…'
                : 'No admin actions recorded yet.'}
            </p>
          ) : (
            <div className='divide-y rounded-lg border bg-card text-xs'>
              {logs.map((item) => (
                <AuditLogRow key={item.id} item={item} />
              ))}
            </div>
          )}
          {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
        </>
      )}
    </AdminPage>
  )
}

export function AdminGatewayLogs() {
  const [page, setPage] = useState(1)
  const logsQuery = useQuery({
    queryKey: adminLogsPageQueryKey(1, page),
    queryFn: () => fetchAdminLogs(1, page),
  })
  const logs = logsQuery.data?.providerLogs ?? []
  const meta = logsQuery.data?.providerMeta

  return (
    <AdminPage
      title='Gateway Logs'
      description='Mobishastra send replies. Use these to see why an SMS did not reach a phone.'
    >
      <p className='mb-3 text-xs text-muted-foreground'>
        blocked = Soscom never called Mobishastra. timeout = no reply. 000 /
        Send Successful = they accepted it. Any other reply is why the phone
        did not get the SMS.
      </p>
      {logsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load gateway logs.</p>
      ) : (
        <>
          {logs.length === 0 ? (
            <p className='text-xs text-muted-foreground'>
              {logsQuery.isLoading ? 'Loading…' : 'No gateway send logs yet.'}
            </p>
          ) : (
            <div className='divide-y rounded-lg border bg-card text-xs'>
              {logs.map((item) => (
                <GatewayLogRow key={item.id} item={item} />
              ))}
            </div>
          )}
          {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
        </>
      )}
    </AdminPage>
  )
}

function AuditLogRow({ item }: { item: AdminAuditLog }) {
  return (
    <div className='flex flex-wrap items-baseline gap-x-3 gap-y-1 px-3 py-2'>
      <span className='shrink-0 text-[11px] text-muted-foreground tabular-nums'>
        {item.createdAt ? formatDateTime(item.createdAt) : '—'}
      </span>
      <Badge variant='outline' className={badgeClass}>
        {item.level}
      </Badge>
      <span className='min-w-0 flex-1'>{item.message}</span>
      {item.actorEmail ? (
        <span className='text-[11px] text-muted-foreground'>
          {item.actorEmail}
        </span>
      ) : null}
    </div>
  )
}

function GatewayLogRow({ item }: { item: AdminProviderLog }) {
  const details = [
    item.campaignName ? `Campaign: ${item.campaignName}` : null,
    item.senderId ? `Sender ID: ${item.senderId}` : null,
    item.phone ? `Phone: ${formatTzMobile(item.phone)}` : null,
    item.countryCode ? `CountryCode: ${item.countryCode}` : null,
    item.httpStatus ? `HTTP: ${item.httpStatus}` : null,
  ].filter(Boolean)

  return (
    <div className='space-y-1 px-3 py-2'>
      <div className='flex flex-wrap items-baseline gap-x-3 gap-y-1'>
        <span className='shrink-0 text-[11px] text-muted-foreground tabular-nums'>
          {item.createdAt ? formatDateTime(item.createdAt) : '—'}
        </span>
        <StatusBadge status={item.status} className={badgeClass} />
        <Badge variant='outline' className={badgeClass}>
          {item.action}
        </Badge>
        <span className='min-w-0 flex-1'>
          {item.diagnosis || item.message}
        </span>
      </div>
      {details.length > 0 ? (
        <p className='text-[11px] text-muted-foreground'>
          {details.join(' · ')}
        </p>
      ) : null}
      {item.providerReply ? (
        <p className='font-mono text-[11px] break-all text-muted-foreground'>
          Mobishastra replied: {item.providerReply}
        </p>
      ) : null}
      {item.error ? (
        <p className='text-[11px] text-destructive'>Error: {item.error}</p>
      ) : null}
    </div>
  )
}
