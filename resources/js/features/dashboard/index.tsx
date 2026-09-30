import { Link } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import {
  BadgeCheck,
  Send,
  Upload,
  Wallet,
} from 'lucide-react'
import { campaignsQueryKey, dashboardStatsQueryKey, fetchCampaigns, fetchDashboardStats } from '@/lib/campaigns'
import {
  creditTransactionsQueryKey,
  fetchCreditTransactions,
} from '@/lib/credits'
import { firstName, formatDateTime, formatNumber } from '@/lib/format'
import { getGreeting } from '@/lib/sms'
import { useAuthStore } from '@/stores/auth-store'
import { needsProfileCompletion } from '@/types/auth'
import { Button } from '@/components/ui/button'
import { AppHeader } from '@/components/soscom/app-header'
import { statusLabel } from '@/components/soscom/status-badge'
import {
  DashboardStatsSkeleton,
  TableSkeleton,
} from '@/components/soscom/skeletons'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { CompleteProfileCard } from './complete-profile-card'

const actions = [
  {
    title: 'Send SMS',
    description: 'Send a message to your customers.',
    to: '/send-sms',
    icon: Send,
  },
  {
    title: 'Buy SMS',
    description: 'Add more SMS to your account.',
    to: '/wallet/buy',
    icon: Wallet,
  },
  {
    title: 'Request Sender ID',
    description: 'Use your own business or organization name.',
    to: '/sender-ids/request',
    icon: BadgeCheck,
  },
  {
    title: 'Import Contacts',
    description: 'Upload your contacts from Excel or CSV.',
    to: '/contacts/import',
    icon: Upload,
  },
] as const

export function Dashboard() {
  const user = useAuthStore((state) => state.user)
  const displayName = user?.name ?? 'there'
  const profileReady = Boolean(user && !needsProfileCompletion(user))
  const statsQuery = useQuery({
    queryKey: dashboardStatsQueryKey,
    queryFn: fetchDashboardStats,
    enabled: profileReady,
  })
  const campaignsQuery = useQuery({
    queryKey: [...campaignsQueryKey, 'recent'],
    queryFn: () => fetchCampaigns({ perPage: 10 }),
    enabled: profileReady,
  })
  const transactionsQuery = useQuery({
    queryKey: [...creditTransactionsQueryKey, 'recent'],
    queryFn: () => fetchCreditTransactions(1),
    enabled: profileReady,
  })
  const campaigns = campaignsQuery.data?.campaigns ?? []
  const transactions = transactionsQuery.data?.transactions ?? []
  const smsQueued = statsQuery.data?.totalSms ?? 0
  const delivered = statsQuery.data?.delivered ?? 0
  const failed = statsQuery.data?.failed ?? 0
  const campaignCount = statsQuery.data?.campaignCount ?? campaigns.length

  return (
    <>
      <AppHeader />
      <Main>
        <div className='mb-6 flex items-start justify-between gap-4'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-semibold tracking-tight'>
              {getGreeting()}, {firstName(displayName)}
            </h1>
            <p className='text-sm text-muted-foreground'>
              {user && needsProfileCompletion(user)
                ? 'Finish your profile to start sending SMS.'
                : "Here's what's happening with your SMS account."}
            </p>
          </div>
          {profileReady ? (
            <Button asChild className='shrink-0'>
              <Link to='/send-sms'>
                <Send />
                Send SMS
              </Link>
            </Button>
          ) : null}
        </div>

        {user && needsProfileCompletion(user) ? (
          <CompleteProfileCard />
        ) : (
        <div className='space-y-6'>
          <div className='grid grid-cols-1 gap-4 lg:grid-cols-3'>
            <div className='rounded-xl border bg-card p-6 lg:col-span-1'>
              <p className='text-sm text-muted-foreground'>SMS Balance</p>
              <p className='mt-2 text-4xl font-semibold tracking-tight'>
                {formatNumber(user?.customer?.smsBalance ?? 0)}{' '}
                <span className='text-lg font-medium text-muted-foreground'>
                  SMS
                </span>
              </p>
              <Button className='mt-6' asChild>
                <Link to='/wallet/buy'>Buy SMS</Link>
              </Button>
            </div>
            {statsQuery.isLoading ? (
              <DashboardStatsSkeleton />
            ) : statsQuery.isError ? (
              <p className='text-sm text-muted-foreground lg:col-span-2'>
                We couldn't load your totals. Refresh the page and try again.
              </p>
            ) : (
            <div className='grid gap-4 sm:grid-cols-2 lg:col-span-2'>
              <Stat label='SMS being sent' value={formatNumber(smsQueued)} />
              <Stat label='Campaigns' value={formatNumber(campaignCount)} />
              <Stat label='Delivered' value={formatNumber(delivered)} />
              <Stat label='Failed' value={formatNumber(failed)} />
            </div>
            )}
          </div>

          <div>
            <h2 className='mb-3 text-lg font-semibold'>
              What would you like to do?
            </h2>
            <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
              {actions.map((action) => (
                <Link
                  key={action.title}
                  to={action.to}
                  className='rounded-xl border bg-card p-5 transition-colors hover:bg-muted/40 active:scale-[0.99] active:bg-muted/60'
                >
                  <action.icon className='mb-4 size-5 text-primary' />
                  <h3 className='font-medium'>{action.title}</h3>
                  <p className='mt-1 text-sm text-muted-foreground'>
                    {action.description}
                  </p>
                </Link>
              ))}
            </div>
          </div>

          <div className='grid grid-cols-1 gap-4 xl:grid-cols-2'>
            <div className='min-w-0'>
              <div className='mb-3 flex items-center justify-between'>
                <h2 className='text-lg font-semibold'>Recent campaigns</h2>
                <Button variant='ghost' size='sm' asChild>
                  <Link to='/campaigns'>View all</Link>
                </Button>
              </div>
              {campaignsQuery.isLoading ? (
                <TableSkeleton rows={4} />
              ) : campaignsQuery.isError ? (
                <p className='text-sm text-muted-foreground'>
                  We couldn't load campaigns. Refresh the page and try again.
                </p>
              ) : (
              <TableWrap>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Campaign</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className='text-end'>Recipients</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {campaigns.slice(0, 4).map((campaign) => (
                      <TableRow key={campaign.id}>
                        <TableCell>
                          <Link
                            to='/campaigns/$campaignId'
                            params={{ campaignId: String(campaign.id) }}
                            className='block max-w-48 truncate font-medium hover:underline sm:max-w-xs'
                            title={campaign.name}
                          >
                            {campaign.name}
                          </Link>
                          <p className='text-xs text-muted-foreground'>
                            {campaign.senderId}
                          </p>
                        </TableCell>
                        <TableCell className='text-sm text-muted-foreground'>
                          {statusLabel(campaign.status)}
                        </TableCell>
                        <TableCell className='text-end'>
                          {formatNumber(campaign.recipients)}
                        </TableCell>
                      </TableRow>
                    ))}
                    {campaigns.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={3}
                          className='text-sm text-muted-foreground'
                        >
                          No campaigns yet. Send your first SMS to see it here.
                        </TableCell>
                      </TableRow>
                    ) : null}
                  </TableBody>
                </Table>
              </TableWrap>
              )}
            </div>

            <div className='min-w-0'>
              <div className='mb-3 flex items-center justify-between'>
                <h2 className='text-lg font-semibold'>Recent transactions</h2>
                <Button variant='ghost' size='sm' asChild>
                  <Link to='/wallet/transactions'>View all</Link>
                </Button>
              </div>
              {transactionsQuery.isLoading ? (
                <TableSkeleton rows={4} />
              ) : transactionsQuery.isError ? (
                <p className='text-sm text-muted-foreground'>
                  We couldn't load transactions. Refresh the page and try again.
                </p>
              ) : (
              <TableWrap>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Description</TableHead>
                      <TableHead>SMS</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {transactions.slice(0, 4).map((item) => (
                      <TableRow key={item.id}>
                        <TableCell>
                          <p
                            className='max-w-48 truncate font-medium sm:max-w-xs'
                            title={item.description}
                          >
                            {item.description}
                          </p>
                          <p className='text-xs text-muted-foreground'>
                            {item.createdAt ? formatDateTime(item.createdAt) : ''}
                          </p>
                        </TableCell>
                        <TableCell>
                          {item.quantity > 0 ? '+' : ''}
                          {formatNumber(item.quantity)}
                        </TableCell>
                        <TableCell className='text-sm text-muted-foreground'>
                          {statusLabel(item.type)}
                        </TableCell>
                      </TableRow>
                    ))}
                    {transactions.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={3}
                          className='text-sm text-muted-foreground'
                        >
                          Purchases will appear here.
                        </TableCell>
                      </TableRow>
                    ) : null}
                  </TableBody>
                </Table>
              </TableWrap>
              )}
            </div>
          </div>
        </div>
        )}
      </Main>
    </>
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
