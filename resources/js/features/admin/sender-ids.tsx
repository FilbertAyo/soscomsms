import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import { formatDate } from '@/lib/format'
import {
  adminSenderIdRequestsQueryKey,
  approveSenderIdRequest,
  fetchAdminSenderIdRequests,
  rejectSenderIdRequest,
  requestSenderIdResubmission,
  reviewSenderIdRequest,
  submitSenderIdRequestToProvider,
  usableSenderIdsQueryKey,
} from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { StatusBadge } from '@/components/soscom/status-badge'
import { RequestTimeline } from '@/components/soscom/request-timeline'
import { SenderIdRequestFacts } from '@/features/sender-ids/request-facts'
import {
  ADMIN_REQUEST_STEPS,
  adminTimelineIndex,
  senderIdUseCaseLabel,
  type SenderIdRequestRecord,
} from '@/types/sender-ids'
import { AdminPage } from './page'

export function AdminSenderIds() {
  const queryClient = useQueryClient()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [listFilter, setListFilter] = useState('')
  const [providerOpen, setProviderOpen] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [resubmitOpen, setResubmitOpen] = useState(false)
  const [submittedAt, setSubmittedAt] = useState(todayInput())
  const [providerReference, setProviderReference] = useState('')
  const [notes, setNotes] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')
  const [resubmitReason, setResubmitReason] = useState('')

  const requestsQuery = useQuery({
    queryKey: adminSenderIdRequestsQueryKey,
    queryFn: fetchAdminSenderIdRequests,
  })
  const requests = requestsQuery.data ?? []
  const selected =
    requests.find((item) => item.id === selectedId) ?? requests[0] ?? null
  const filterTerm = listFilter.trim().toLowerCase()
  const visibleRequests = filterTerm
    ? requests.filter((item) =>
        [item.name, item.customerBusiness, item.customerName].some((value) =>
          value?.toLowerCase().includes(filterTerm)
        )
      )
    : requests

  async function refresh() {
    void queryClient.invalidateQueries({ queryKey: adminSenderIdRequestsQueryKey })
    void queryClient.invalidateQueries({ queryKey: usableSenderIdsQueryKey })
    void queryClient.invalidateQueries({ queryKey: ['sender-id-requests'] })
  }

  const reviewMutation = useMutation({
    mutationFn: (id: number) => reviewSenderIdRequest(id),
    onSuccess: async () => {
      toast.success('Request marked under review.')
      await refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const submitMutation = useMutation({
    mutationFn: (id: number) =>
      submitSenderIdRequestToProvider(id, {
        submitted_to_provider_at: submittedAt || undefined,
        provider_reference: providerReference.trim() || undefined,
        notes: notes.trim() || undefined,
      }),
    onSuccess: async () => {
      toast.success('Marked as submitted to Mobishastra.')
      setProviderOpen(false)
      setProviderReference('')
      setNotes('')
      await refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const approveMutation = useMutation({
    mutationFn: (id: number) => approveSenderIdRequest(id),
    onSuccess: async (request) => {
      toast.success(`${request.name} is now active for this customer.`)
      await refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const rejectMutation = useMutation({
    mutationFn: (id: number) => rejectSenderIdRequest(id, rejectionReason.trim()),
    onSuccess: async () => {
      toast.success('Request rejected. The customer will be emailed.')
      setRejectOpen(false)
      setRejectionReason('')
      await refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const resubmitMutation = useMutation({
    mutationFn: (id: number) =>
      requestSenderIdResubmission(id, resubmitReason.trim()),
    onSuccess: async () => {
      toast.success('Asked the customer to upload a new document.')
      setResubmitOpen(false)
      setResubmitReason('')
      await refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  return (
    <AdminPage
      title='Sender ID Requests'
      description='Review customer requests, submit them to Mobishastra outside this system, then record the outcome here. No provider API call is made.'
    >
      {requestsQuery.isError ? (
        <p className='text-sm text-destructive'>Could not load requests.</p>
      ) : (
        <div className='grid items-start gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]'>
          <div className='rounded-lg border bg-card'>
            <div className='border-b px-3 py-2 text-xs font-medium'>
              {requests.length === 1
                ? '1 request'
                : `${requests.length} requests`}
            </div>
            <div className='border-b p-2'>
              <div className='relative'>
                <Search className='pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground' />
                <Input
                  type='search'
                  value={listFilter}
                  onChange={(event) => setListFilter(event.target.value)}
                  placeholder='Filter by name or customer'
                  className='h-8 ps-8 text-xs'
                  aria-label='Filter requests'
                />
              </div>
            </div>
            {requests.length === 0 ? (
              <p className='p-3 text-xs text-muted-foreground'>
                No Sender ID requests yet.
              </p>
            ) : visibleRequests.length === 0 ? (
              <p className='p-3 text-xs text-muted-foreground'>
                No requests match “{listFilter.trim()}”.
              </p>
            ) : (
              <div className='max-h-[28rem] divide-y overflow-y-auto'>
                {visibleRequests.map((item) => (
                  <button
                    key={item.id}
                    type='button'
                    onClick={() => setSelectedId(item.id)}
                    className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-start transition-colors hover:bg-muted/50 ${
                      selected?.id === item.id
                        ? 'bg-primary/10 hover:bg-primary/10'
                        : ''
                    }`}
                  >
                    <span className='min-w-0'>
                      <span className='block truncate text-sm font-medium'>
                        {item.name}
                      </span>
                      <span className='block truncate text-[11px] text-muted-foreground'>
                        {item.customerBusiness ?? item.customerName} ·{' '}
                        {item.createdAt ? formatDate(item.createdAt) : '—'}
                      </span>
                    </span>
                    <StatusBadge
                      status={item.status}
                      className='shrink-0 px-1.5 py-0 text-[10px] leading-4'
                    />
                  </button>
                ))}
              </div>
            )}
          </div>
          {selected ? (
            <RequestDetail
              request={selected}
              pending={
                reviewMutation.isPending ||
                submitMutation.isPending ||
                approveMutation.isPending ||
                rejectMutation.isPending ||
                resubmitMutation.isPending
              }
              onReview={() => reviewMutation.mutate(selected.id)}
              onOpenProvider={() => {
                setSubmittedAt(todayInput())
                setProviderOpen(true)
              }}
              onApprove={() => approveMutation.mutate(selected.id)}
              onOpenReject={() => setRejectOpen(true)}
              onOpenResubmit={() => {
                setResubmitReason(
                  'The identity PDF could not be opened. Please upload a clear 1–3 page scan.'
                )
                setResubmitOpen(true)
              }}
            />
          ) : null}
        </div>
      )}

      <Dialog open={providerOpen} onOpenChange={setProviderOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Mark submitted to Mobishastra</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <p className='text-sm text-muted-foreground'>
              Do this after you have emailed or logged the request with
              Mobishastra. Nothing is sent from this screen.
            </p>
            <div className='space-y-2'>
              <Label htmlFor='submitted-at'>Date submitted</Label>
              <Input
                id='submitted-at'
                type='date'
                value={submittedAt}
                onChange={(event) => setSubmittedAt(event.target.value)}
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='provider-ref'>Provider reference (optional)</Label>
              <Input
                id='provider-ref'
                value={providerReference}
                onChange={(event) => setProviderReference(event.target.value)}
                placeholder='Email thread or ticket ID'
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='provider-notes'>Notes (optional)</Label>
              <Textarea
                id='provider-notes'
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setProviderOpen(false)}>
              Cancel
            </Button>
            <Button
              disabled={submitMutation.isPending || selected === null}
              onClick={() => selected && submitMutation.mutate(selected.id)}
            >
              {submitMutation.isPending ? 'Saving…' : 'Save'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject {selected?.name}</DialogTitle>
          </DialogHeader>
          <div className='space-y-2'>
            <p className='text-sm text-muted-foreground'>
              This closes the request. The customer will get an email with your
              reason.
            </p>
            <Label htmlFor='reject-reason'>Reason</Label>
            <Textarea
              id='reject-reason'
              value={rejectionReason}
              onChange={(event) => setRejectionReason(event.target.value)}
              placeholder='Mobishastra declined this name because…'
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setRejectOpen(false)}>
              Cancel
            </Button>
            <Button
              variant='destructive'
              disabled={
                rejectMutation.isPending ||
                rejectionReason.trim() === '' ||
                selected === null
              }
              onClick={() => selected && rejectMutation.mutate(selected.id)}
            >
              {rejectMutation.isPending ? 'Saving…' : 'Reject'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={resubmitOpen} onOpenChange={setResubmitOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Ask to resubmit {selected?.name}</DialogTitle>
          </DialogHeader>
          <div className='space-y-2'>
            <p className='text-sm text-muted-foreground'>
              Use this when the identity PDF is empty or cannot be opened. The
              customer will get an email and can upload a new file.
            </p>
            <Label htmlFor='resubmit-reason'>Reason</Label>
            <Textarea
              id='resubmit-reason'
              value={resubmitReason}
              onChange={(event) => setResubmitReason(event.target.value)}
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setResubmitOpen(false)}>
              Cancel
            </Button>
            <Button
              disabled={
                resubmitMutation.isPending ||
                resubmitReason.trim() === '' ||
                selected === null
              }
              onClick={() => selected && resubmitMutation.mutate(selected.id)}
            >
              {resubmitMutation.isPending ? 'Saving…' : 'Ask to resubmit'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AdminPage>
  )
}

function RequestDetail({
  request,
  pending,
  onReview,
  onOpenProvider,
  onApprove,
  onOpenReject,
  onOpenResubmit,
}: {
  request: SenderIdRequestRecord
  pending: boolean
  onReview: () => void
  onOpenProvider: () => void
  onApprove: () => void
  onOpenReject: () => void
  onOpenResubmit: () => void
}) {
  const open = request.status !== 'approved' && request.status !== 'rejected'
  const waitingOnCustomer = request.status === 'needs_resubmission'
  const canReview =
    request.status === 'submitted' || request.status === 'under_review'
  const canSubmitToProvider =
    request.status === 'submitted' || request.status === 'under_review'
  const canResolve = request.status === 'submitted_to_provider'

  return (
    <div className='rounded-lg border bg-card p-4'>
      <div className='flex items-start justify-between gap-3'>
        <div className='min-w-0'>
          <h2 className='truncate text-base font-semibold'>{request.name}</h2>
          <p className='text-sm text-muted-foreground'>
            {senderIdUseCaseLabel(request.purpose)}
          </p>
          <p className='mt-1 text-sm text-muted-foreground'>
            {request.customerBusiness ?? request.customerName} ·{' '}
            {request.organization}
          </p>
        </div>
        <StatusBadge status={request.status} />
      </div>
      <SenderIdRequestFacts request={request} admin />
      {request.providerReference ? (
        <p className='mt-3 text-sm text-muted-foreground'>
          Provider reference: {request.providerReference}
        </p>
      ) : null}
      {waitingOnCustomer ? (
        <p className='mt-3 text-sm text-amber-700 dark:text-amber-400'>
          Waiting for a new identity PDF.
          {request.rejectionReason ? ` ${request.rejectionReason}` : ''}
        </p>
      ) : request.rejectionReason ? (
        <p className='mt-3 text-sm text-destructive'>{request.rejectionReason}</p>
      ) : null}
      <div className='mt-6'>
        <RequestTimeline
          steps={[...ADMIN_REQUEST_STEPS]}
          currentIndex={adminTimelineIndex(request)}
        />
      </div>
      {open ? (
        <div className='mt-6 flex flex-wrap gap-2'>
          {canReview ? (
            <Button disabled={pending} onClick={onReview}>
              Mark under review
            </Button>
          ) : null}
          {canSubmitToProvider ? (
            <Button variant='outline' disabled={pending} onClick={onOpenProvider}>
              Submitted to Mobishastra
            </Button>
          ) : null}
          {canResolve ? (
            <Button disabled={pending} onClick={onApprove}>
              Approve
            </Button>
          ) : null}
          <Button variant='outline' disabled={pending} onClick={onOpenResubmit}>
            Ask to resubmit
          </Button>
          <Button variant='outline' disabled={pending} onClick={onOpenReject}>
            Reject
          </Button>
        </div>
      ) : null}
    </div>
  )
}

function todayInput(): string {
  return new Date().toISOString().slice(0, 10)
}
