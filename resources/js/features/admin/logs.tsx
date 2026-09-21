import { useQuery } from '@tanstack/react-query'
import { adminLogsQueryKey, fetchAdminLogs } from '@/lib/admin'
import { formatDateTime } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { AdminPage } from './page'

export function AdminLogs() {
  const logsQuery = useQuery({
    queryKey: adminLogsQueryKey,
    queryFn: fetchAdminLogs,
  })
  const auditLogs = logsQuery.data?.auditLogs ?? []
  const providerLogs = logsQuery.data?.providerLogs ?? []

  return (
    <AdminPage
      title='System Logs'
      description='Admin audit trail and gateway send attempts. Provider secrets are never shown.'
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
          </section>
          <section>
            <h2 className='mb-3 font-semibold'>Gateway</h2>
            <div className='space-y-3'>
              {providerLogs.length === 0 ? (
                <p className='text-sm text-muted-foreground'>
                  No gateway send logs yet.
                </p>
              ) : (
                providerLogs.map((item) => (
                  <div key={`provider-${item.id}`} className='rounded-xl border bg-card p-4'>
                    <div className='mb-2 flex items-center gap-2'>
                      <Badge variant='outline'>{item.status}</Badge>
                      <span className='text-xs text-muted-foreground'>
                        {item.createdAt ? formatDateTime(item.createdAt) : '—'}
                      </span>
                    </div>
                    <p className='text-sm'>{item.message}</p>
                  </div>
                ))
              )}
            </div>
          </section>
        </div>
      )}
    </AdminPage>
  )
}
