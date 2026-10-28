import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { adminCustomersSearchQueryKey } from '@/lib/admin'
import { fetchAdminCustomers } from '@/lib/credits'
import { formatNumber } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { ListPagination } from '@/components/soscom/list-pagination'
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

export function AdminCustomers() {
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const customersQuery = useQuery({
    queryKey: adminCustomersSearchQueryKey(`${q}:${page}`),
    queryFn: () => fetchAdminCustomers(q, page),
  })
  const customers = customersQuery.data?.customers ?? []
  const meta = customersQuery.data?.meta

  return (
    <AdminPage
      title='Customers'
      description='Search accounts and open a customer to view details, grant credits, or change status.'
    >
      <div className='mb-4 max-w-md'>
        <Input
          value={q}
          onChange={(event) => {
            setQ(event.target.value)
            setPage(1)
          }}
          placeholder='Search name, email, business, or phone'
        />
      </div>
      {customersQuery.isError ? (
        <QueryLoadError
          message="We couldn't load customers."
          onRetry={() => void customersQuery.refetch()}
        />
      ) : (
        <TableWrap>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Business</TableHead>
                <TableHead>SMS Balance</TableHead>
                <TableHead>Sent this month</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='text-end'>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {customers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className='text-muted-foreground'>
                    {q ? 'No customers match that search.' : 'No customers yet.'}
                  </TableCell>
                </TableRow>
              ) : (
                customers.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <p className='font-medium'>{item.name}</p>
                      <p className='text-xs text-muted-foreground'>{item.email}</p>
                    </TableCell>
                    <TableCell>{item.businessName ?? '—'}</TableCell>
                    <TableCell>{formatNumber(item.smsBalance)}</TableCell>
                    <TableCell>{formatNumber(item.sentThisMonth)}</TableCell>
                    <TableCell>
                      <StatusBadge status={item.status} />
                    </TableCell>
                    <TableCell className='text-end'>
                      <Button size='sm' variant='ghost' asChild>
                        <Link
                          to='/admin/customers/$customerId'
                          params={{ customerId: String(item.id) }}
                        >
                          View
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableWrap>
      )}
      {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
    </AdminPage>
  )
}
