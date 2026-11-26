import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { toast } from 'sonner'
import { Download, FileArchive, FileSpreadsheet } from 'lucide-react'
import {
  campaignsQueryKey,
  downloadOldDeliveryReports,
  fetchCampaigns,
} from '@/lib/campaigns'
import { appDateDaysAgo, appDateInputValue } from '@/lib/format'
import { fetchUsableSenderIds, usableSenderIdsQueryKey } from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'

const columns = [
  'Recipient',
  'Campaign',
  'Sender ID',
  'Message',
  'Status',
  'Sent at',
]

export function OldDeliveryReportsPage() {
  const [from, setFrom] = useState(() => appDateDaysAgo(90))
  const [to, setTo] = useState(() => appDateInputValue())
  const [campaign, setCampaign] = useState('all')
  const [senderId, setSenderId] = useState('all')
  const [status, setStatus] = useState('all')
  const [recipient, setRecipient] = useState('')
  const [downloading, setDownloading] = useState(false)
  const rangeInvalid = from !== '' && to !== '' && from > to

  const campaignsQuery = useQuery({
    queryKey: campaignsQueryKey,
    queryFn: () => fetchCampaigns({ perPage: 100 }),
  })
  const senderIdsQuery = useQuery({
    queryKey: usableSenderIdsQueryKey,
    queryFn: fetchUsableSenderIds,
  })
  const campaigns = campaignsQuery.data?.campaigns ?? []
  const senderIds = senderIdsQuery.data ?? []

  async function onDownload(event: FormEvent) {
    event.preventDefault()
    if (from === '' || to === '') {
      toast.error('Choose a from date and a to date.')
      return
    }
    if (rangeInvalid) {
      toast.error('From must be on or before to.')
      return
    }

    setDownloading(true)
    try {
      await downloadOldDeliveryReports({
        from,
        to,
        campaignId: campaign === 'all' ? '' : campaign,
        senderId: senderId === 'all' ? '' : senderId,
        status: status === 'all' ? '' : status,
        q: recipient.trim(),
      })
      toast.success('Zip downloaded. Open it to find the Excel file.')
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : 'Could not download the delivery report.'
      )
    } finally {
      setDownloading(false)
    }
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Delivery report Old'
          description='Pick any date range and download the records. Nothing is listed here. You get a zip file with an Excel workbook inside.'
        />
        <div className='grid items-start gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.8fr)]'>
          <form
            onSubmit={(event) => void onDownload(event)}
            className='rounded-2xl border bg-card p-5 shadow-sm sm:p-6'
          >
            <div className='flex items-start gap-3'>
              <span className='flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary'>
                <FileArchive className='size-5' />
              </span>
              <div>
                <h2 className='font-medium'>Date range</h2>
                <p className='mt-1 text-sm text-muted-foreground'>
                  Any interval is allowed, including dates older than 30 days.
                  The last 30 days are still listed on{' '}
                  <Link
                    to='/reports'
                    className='font-medium text-foreground underline-offset-4 hover:underline'
                  >
                    Delivery reports
                  </Link>
                  .
                </p>
              </div>
            </div>

            <div className='mt-5 grid gap-4 sm:grid-cols-2'>
              <div className='space-y-2'>
                <Label htmlFor='old-report-from'>From</Label>
                <Input
                  id='old-report-from'
                  type='date'
                  required
                  value={from}
                  max={to || undefined}
                  onChange={(event) => setFrom(event.target.value)}
                />
              </div>
              <div className='space-y-2'>
                <Label htmlFor='old-report-to'>To</Label>
                <Input
                  id='old-report-to'
                  type='date'
                  required
                  value={to}
                  min={from || undefined}
                  onChange={(event) => setTo(event.target.value)}
                />
              </div>
            </div>
            {rangeInvalid ? (
              <p className='mt-2 text-sm text-destructive'>
                From must be on or before to.
              </p>
            ) : null}

            <div className='mt-6 rounded-xl border bg-muted/30 p-4'>
              <p className='text-sm font-medium'>Optional filters</p>
              <p className='mt-1 text-sm text-muted-foreground'>
                Leave these open to include every message in the range.
              </p>
              <div className='mt-4 grid gap-3 sm:grid-cols-2'>
                <Select value={campaign} onValueChange={setCampaign}>
                  <SelectTrigger aria-label='Campaign'>
                    <SelectValue placeholder='Campaign' />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='all'>All campaigns</SelectItem>
                    {campaigns.map((item) => (
                      <SelectItem key={item.id} value={String(item.id)}>
                        {item.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select value={senderId} onValueChange={setSenderId}>
                  <SelectTrigger aria-label='Sender ID'>
                    <SelectValue placeholder='Sender ID' />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='all'>All Sender IDs</SelectItem>
                    {senderIds.map((item) => (
                      <SelectItem key={item.id} value={item.name}>
                        {item.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select value={status} onValueChange={setStatus}>
                  <SelectTrigger aria-label='Status'>
                    <SelectValue placeholder='Status' />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='all'>All statuses</SelectItem>
                    <SelectItem value='delivered'>Delivered</SelectItem>
                    <SelectItem value='sent'>Sent</SelectItem>
                    <SelectItem value='failed'>Failed</SelectItem>
                    <SelectItem value='rejected'>Rejected</SelectItem>
                  </SelectContent>
                </Select>
                <Input
                  placeholder='Recipient'
                  aria-label='Recipient'
                  value={recipient}
                  onChange={(event) => setRecipient(event.target.value)}
                />
              </div>
            </div>

            <div className='mt-6 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between'>
              <p className='text-sm text-muted-foreground'>
                {from && to && !rangeInvalid
                  ? `delivery-reports-${from}-to-${to}.zip`
                  : 'Choose both dates to name the file.'}
              </p>
              <Button type='submit' disabled={downloading || rangeInvalid}>
                <Download />
                {downloading ? 'Preparing zip…' : 'Download zip'}
              </Button>
            </div>
          </form>

          <aside className='rounded-2xl border bg-muted/40 p-5 sm:p-6'>
            <div className='flex items-center gap-2'>
              <FileSpreadsheet className='size-4 text-primary' />
              <h2 className='font-medium'>What you receive</h2>
            </div>
            <ol className='mt-4 space-y-3 text-sm text-muted-foreground'>
              <li>
                <span className='font-medium text-foreground'>1. </span>
                The zip file saves to your downloads.
              </li>
              <li>
                <span className='font-medium text-foreground'>2. </span>
                Open the zip and find{' '}
                <span className='font-medium text-foreground'>
                  delivery-reports.xlsx
                </span>
                .
              </li>
              <li>
                <span className='font-medium text-foreground'>3. </span>
                Open that Excel file to read the messages.
              </li>
            </ol>
            <p className='mt-5 text-sm font-medium'>Columns</p>
            <ul className='mt-2 space-y-1 text-sm text-muted-foreground'>
              {columns.map((column) => (
                <li key={column}>{column}</li>
              ))}
            </ul>
            <p className='mt-5 text-sm text-muted-foreground'>
              One download can hold up to 50,000 messages. If a range is larger,
              shorten the dates and download it in parts. Times look like{' '}
              <span className='font-medium text-foreground'>
                25 Nov 2026, 08:16
              </span>
              .
            </p>
          </aside>
        </div>
      </Main>
    </>
  )
}
