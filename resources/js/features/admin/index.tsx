import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { formatNumber, formatTZS } from '@/lib/format'
import {
  adminOverviewQueryKey,
  fetchAdminOverview,
} from '@/lib/admin'
import {
  adminProviderBalanceQueryKey,
  fetchAdminProviderBalance,
} from '@/lib/credits'
import {
  adminSenderIdRequestsQueryKey,
  fetchAdminSenderIdRequests,
} from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/soscom/status-badge'
import { AdminPage } from './page'

export function AdminOverview() {
  const overviewQuery = useQuery({
    queryKey: adminOverviewQueryKey,
    queryFn: fetchAdminOverview,
  })
  const requestsQuery = useQuery({
    queryKey: adminSenderIdRequestsQueryKey,
    queryFn: fetchAdminSenderIdRequests,
  })
  const providerQuery = useQuery({
    queryKey: adminProviderBalanceQueryKey,
    queryFn: fetchAdminProviderBalance,
  })
  const overview = overviewQuery.data
  const senderRequests = requestsQuery.data ?? []
  const recentCustomers = overview?.recentCustomers ?? []
  const recentLogs = overview?.recentAuditLogs ?? []

  return (
    <AdminPage
      title='Overview'
      description='Live customers, SMS traffic, confirmed purchase revenue, and Sender ID requests.'
    >
      {overviewQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load admin overview.</p>
      ) : (
        <>
          <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-3'>
            <Stat label='Customers' value={formatNumber(overview?.customers ?? 0)} />
            <Stat
              label='SMS traffic'
              value={formatNumber(overview?.smsTraffic ?? 0)}
            />
            <Stat
              label='Revenue'
              value={formatTZS(overview?.purchaseRevenueTzs ?? 0)}
            />
            <Stat
              label='SMS provider balance'
              value={formatNumber(providerQuery.data?.smsBalance ?? 0)}
              hint='Credits still on Mobishastra.'
            />
            <Stat
              label='Customer credits'
              value={formatNumber(providerQuery.data?.customerCredits ?? 0)}
              hint='Unused signup bonuses and purchases.'
            />
            <Stat
              label='Real remaining'
              value={formatNumber(providerQuery.data?.realSmsBalance ?? 0)}
              hint='Provider remaining minus customer credits.'
              warn={(providerQuery.data?.realSmsBalance ?? 0) < 0}
            />
          </div>
          <div className='mt-6 grid gap-4 xl:grid-cols-2'>
            <section className='rounded-xl border bg-card p-5'>
              <div className='mb-4 flex items-center justify-between'>
                <h2 className='font-semibold'>Sender ID requests</h2>
                <Button variant='ghost' size='sm' asChild>
                  <Link to='/admin/sender-ids'>Review</Link>
                </Button>
              </div>
              <div className='space-y-3'>
                {senderRequests.length === 0 ? (
                  <p className='text-sm text-muted-foreground'>
                    No Sender ID requests yet.
                  </p>
                ) : (
                  senderRequests.slice(0, 5).map((item) => (
                    <div
                      key={item.id}
                      className='flex items-center justify-between gap-3'
                    >
                      <div>
                        <p className='font-medium'>{item.name}</p>
                        <p className='text-xs text-muted-foreground'>
                          {item.customerBusiness ?? item.customerName}
                        </p>
                      </div>
                      <StatusBadge status={item.status} />
                    </div>
                  ))
                )}
              </div>
            </section>
            <section className='rounded-xl border bg-card p-5'>
              <div className='mb-4 flex items-center justify-between'>
                <h2 className='font-semibold'>Customers</h2>
                <Button variant='ghost' size='sm' asChild>
                  <Link to='/admin/customers'>View all</Link>
                </Button>
              </div>
              <div className='space-y-3'>
                {recentCustomers.length === 0 ? (
                  <p className='text-sm text-muted-foreground'>
                    No customers yet.
                  </p>
                ) : (
                  recentCustomers.map((item) => (
                    <div
                      key={item.id}
                      className='flex items-center justify-between gap-3'
                    >
                      <div>
                        <p className='font-medium'>
                          {item.businessName ?? item.name}
                        </p>
                        <p className='text-xs text-muted-foreground'>
                          {item.email}
                        </p>
                      </div>
                      <StatusBadge status={item.status} />
                    </div>
                  ))
                )}
              </div>
            </section>
          </div>
          <section className='mt-6 rounded-xl border bg-card p-5'>
            <div className='mb-4 flex items-center justify-between'>
              <h2 className='font-semibold'>Audit log</h2>
              <Button variant='ghost' size='sm' asChild>
                <Link to='/admin/logs'>View all</Link>
              </Button>
            </div>
            <div className='space-y-3'>
              {recentLogs.length === 0 ? (
                <p className='text-sm text-muted-foreground'>
                  No admin actions recorded yet.
                </p>
              ) : (
                recentLogs.slice(0, 5).map((item) => (
                  <p key={item.id} className='text-sm text-muted-foreground'>
                    {item.message}
                  </p>
                ))
              )}
            </div>
          </section>
        </>
      )}
    </AdminPage>
  )
}

function Stat({
  label,
  value,
  hint,
  warn = false,
}: {
  label: string
  value: string
  hint?: string
  warn?: boolean
}) {
  return (
    <div className='rounded-xl border bg-card p-5'>
      <p className='text-sm text-muted-foreground'>{label}</p>
      <p className={`mt-2 text-2xl font-semibold ${warn ? 'text-destructive' : ''}`}>
        {value}
      </p>
      {hint ? (
        <p className='mt-1 text-xs text-muted-foreground'>{hint}</p>
      ) : null}
    </div>
  )
}
