import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { adminTrafficQueryKey, fetchAdminTraffic } from '@/lib/admin'
import { formatNumber } from '@/lib/format'
import { StatusBadge } from '@/components/soscom/status-badge'
import { ListPagination } from '@/components/soscom/list-pagination'
import { TableWrap } from '@/components/soscom/table-wrap'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { AdminPage } from './page'

export function AdminTraffic() {
  const [page, setPage] = useState(1)
  const trafficQuery = useQuery({
    queryKey: [...adminTrafficQueryKey, page],
    queryFn: () => fetchAdminTraffic(page),
  })
  const traffic = trafficQuery.data
  const campaigns = traffic?.campaigns ?? []
  const meta = traffic?.meta

  return (
    <AdminPage
      title='SMS Traffic'
      description='Campaign volume across Soscom SMS.'
    >
      {trafficQuery.isError ? (
        <QueryLoadError
          message="We couldn't load SMS traffic."
          onRetry={() => void trafficQuery.refetch()}
        />
      ) : (
        <>
          <div className='mb-6 grid gap-4 sm:grid-cols-3'>
            <Stat
              label='Total SMS'
              value={formatNumber(traffic?.smsTraffic ?? 0)}
            />
            <Stat
              label='This month'
              value={formatNumber(traffic?.smsThisMonth ?? 0)}
            />
            <Stat
              label='Failed deliveries'
              value={formatNumber(traffic?.failedDeliveries ?? 0)}
            />
          </div>
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Campaign</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Sender ID</TableHead>
                  <TableHead>SMS</TableHead>
                  <TableHead>Recipients</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {campaigns.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className='text-muted-foreground'>
                      No campaigns yet.
                    </TableCell>
                  </TableRow>
                ) : (
                  campaigns.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className='font-medium'>{item.name}</TableCell>
                      <TableCell>
                        <p>{item.customerBusiness ?? item.customerName ?? '—'}</p>
                        <p className='text-xs text-muted-foreground'>
                          {item.customerEmail}
                        </p>
                      </TableCell>
                      <TableCell>{item.senderId}</TableCell>
                      <TableCell>{formatNumber(item.totalSms)}</TableCell>
                      <TableCell>{formatNumber(item.recipients)}</TableCell>
                      <TableCell>
                        <StatusBadge status={item.status} />
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableWrap>
          {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
        </>
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
