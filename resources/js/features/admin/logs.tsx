import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { adminLogsPageQueryKey, fetchAdminLogs } from '@/lib/admin'
import { formatDateTime, formatTzMobile } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { ListPagination } from '@/components/soscom/list-pagination'
import { StatusBadge } from '@/components/soscom/status-badge'
import { AdminPage } from './page'
import { type AdminProviderLog } from '@/types/admin'

export function AdminLogs() {
  const [auditPage, setAuditPage] = useState(1)
  const [providerPage, setProviderPage] = useState(1)
  const logsQuery = useQuery({
    queryKey: adminLogsPageQueryKey(auditPage, providerPage),
    queryFn: () => fetchAdminLogs(auditPage, providerPage),
  })
  const auditLogs = logsQuery.data?.auditLogs ?? []
  const providerLogs = logsQuery.data?.providerLogs ?? []
  const auditMeta = logsQuery.data?.auditMeta
  const providerMeta = logsQuery.data?.providerMeta

  return (
    <AdminPage
      title='System Logs'
      description='Admin audit trail and Mobishastra send replies. Use the gateway column to see why an SMS did not reach a phone. Passwords are never shown.'
    >
      {logsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load logs.</p>
      ) : (
        <div className='grid gap-6 xl:grid-cols-2'>
          <section>
            <h2 className='mb-3 font-semibold'>Audit</h2>
            <div className='space-y-3'>
              {auditLogs.length === 0 ? (
                <p className='text-sm text-muted-foreground'>
                  No admin actions recorded yet.
                </p>
              ) : (
                auditLogs.map((item) => (
                  <div key={`audit-${item.id}`} className='rounded-xl border bg-card p-4'>
                    <div className='mb-2 flex items-center gap-2'>
                      <Badge variant='outline'>{item.level}</Badge>
                      <span className='text-xs text-muted-foreground'>
                        {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                      </span>
                    </div>
                    <p className='text-sm'>{item.message}</p>
                    {item.actorEmail ? (
                      <p className='mt-1 text-xs text-muted-foreground'>
                        {item.actorEmail}
                      </p>
                    ) : null}
                  </div>
                ))
              )}
            </div>
            {auditMeta ? (
              <ListPagination meta={auditMeta} onPageChange={setAuditPage} />
            ) : null}
          </section>
          <section>
            <h2 className='mb-3 font-semibold'>Gateway</h2>
            <p className='mb-3 text-xs text-muted-foreground'>
              blocked = Soscom never called Mobishastra. timeout = no reply.
              000 / Send Successful = they accepted it. Any other reply is why
              the phone did not get the SMS.
            </p>
            <div className='space-y-3'>
              {providerLogs.length === 0 ? (
                <p className='text-sm text-muted-foreground'>
                  No gateway send logs yet.
                </p>
              ) : (
                providerLogs.map((item) => (
                  <GatewayLogCard key={`provider-${item.id}`} item={item} />
                ))
              )}
            </div>
            {providerMeta ? (
              <ListPagination
                meta={providerMeta}
                onPageChange={setProviderPage}
              />
            ) : null}
          </section>
        </div>
      )}
    </AdminPage>
  )
}

function GatewayLogCard({ item }: { item: AdminProviderLog }) {
  return (
    <div className='rounded-xl border bg-card p-4'>
      <div className='mb-2 flex flex-wrap items-center gap-2'>
        <StatusBadge status={item.status} />
        <Badge variant='outline'>{item.action}</Badge>
        <span className='text-xs text-muted-foreground'>
          {item.createdAt ? formatDateTime(item.createdAt) : '—'}
        </span>
      </div>
      {item.diagnosis ? (
        <p className='text-sm'>{item.diagnosis}</p>
      ) : (
        <p className='text-sm'>{item.message}</p>
      )}
      <dl className='mt-3 space-y-1 text-xs text-muted-foreground'>
        {item.campaignName ? (
          <div>Campaign: {item.campaignName}</div>
        ) : null}
        {item.senderId ? <div>Sender ID: {item.senderId}</div> : null}
        {item.phone ? <div>Phone: {formatTzMobile(item.phone)}</div> : null}
        {item.countryCode ? <div>CountryCode: {item.countryCode}</div> : null}
        {item.httpStatus ? <div>HTTP: {item.httpStatus}</div> : null}
        {item.providerReply ? (
          <div className='break-all font-mono'>
            Mobishastra replied: {item.providerReply}
          </div>
        ) : null}
        {item.error ? <div>Error: {item.error}</div> : null}
      </dl>
    </div>
  )
}
