import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  assertIdentityPdf,
  fetchSenderIdRequest,
  formatIdentityFileSize,
  resubmitSenderIdDocument,
  senderIdRequestQueryKey,
  senderIdRequestsQueryKey,
} from '@/lib/sender-ids'
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
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { RequestTimeline } from '@/components/soscom/request-timeline'
import { PanelSkeleton } from '@/components/soscom/skeletons'
import { StatusBadge } from '@/components/soscom/status-badge'
import { Main } from '@/components/layout/main'
import { SenderIdRequestFacts } from '@/features/sender-ids/request-facts'
import {
  CUSTOMER_REQUEST_STEPS,
  IDENTITY_DOCUMENT_TYPES,
  senderIdUseCaseLabel,
  type SenderIdRequestRecord,
} from '@/types/sender-ids'

export function SenderIdRequestDetail({ senderId }: { senderId: string }) {
  const requestQuery = useQuery({
    queryKey: senderIdRequestQueryKey(senderId),
    queryFn: () => fetchSenderIdRequest(senderId),
    enabled: /^\d+$/.test(senderId),
  })
  const item = requestQuery.data

  if (!/^\d+$/.test(senderId) || (requestQuery.isError && !item)) {
    return (
      <>
        <AppHeader />
        <Main>
          <EmptyState
            title='Request not found'
            description='This Sender ID request does not belong to your account.'
            actionLabel='Back to Sender IDs'
            actionTo='/sender-ids'
          />
        </Main>
      </>
    )
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title={`Sender ID: ${item?.name ?? '…'}`}
          description={
            item
              ? `${item.organization} · ${senderIdUseCaseLabel(item.purpose)}`
              : 'Loading this request.'
          }
          actions={
            <Button variant='outline' asChild>
              <Link to='/sender-ids'>My Sender IDs</Link>
            </Button>
          }
        />
        {item ? (
          <div className='max-w-xl rounded-xl border bg-card p-5'>
            <StatusBadge status={item.status} />
            {item.status === 'rejected' && item.rejectionReason ? (
              <p className='mt-4 text-sm text-destructive'>
                {item.rejectionReason}
              </p>
            ) : null}
            {item.status === 'needs_resubmission' ? (
              <div className='mt-4 space-y-2'>
                <p className='text-sm text-destructive'>
                  {item.rejectionReason ||
                    'Please upload a new identity PDF. The previous file could not be opened.'}
                </p>
                <ResubmitDocumentForm request={item} />
              </div>
            ) : null}
            <SenderIdRequestFacts request={item} />
            {item.submittedToProviderAt &&
            item.status !== 'needs_resubmission' &&
            item.status !== 'rejected' ? (
              <p className='mt-3 text-sm text-muted-foreground'>
                Soscom is reviewing this name. We will update this page when it
                is approved or declined.
              </p>
            ) : null}
            <div className='mt-6'>
              <RequestTimeline
                steps={[...CUSTOMER_REQUEST_STEPS]}
                currentIndex={item.timelineIndex}
              />
            </div>
            {item.status === 'approved' ? (
              <Button className='mt-6' asChild>
                <Link to='/send-sms'>Use Sender ID</Link>
              </Button>
            ) : null}
          </div>
        ) : (
          <PanelSkeleton lines={4} className='max-w-xl' />
        )}
      </Main>
    </>
  )
}

function ResubmitDocumentForm({ request }: { request: SenderIdRequestRecord }) {
  const queryClient = useQueryClient()
  const [identityType, setIdentityType] = useState(
    request.identityDocumentType ?? ''
  )
  const [document, setDocument] = useState<File | null>(null)
  const [fileInputKey, setFileInputKey] = useState(0)

  const submitMutation = useMutation({
    mutationFn: () => {
      if (!document) {
        return Promise.reject(
          new Error('Upload a PDF of the selected identity document.')
        )
      }
      return resubmitSenderIdDocument(request.id, {
        identityDocumentType: identityType,
        identityDocument: document,
      })
    },
    onSuccess: async () => {
      toast.success('New document submitted.')
      void queryClient.invalidateQueries({ queryKey: senderIdRequestsQueryKey })
      void queryClient.invalidateQueries({
        queryKey: senderIdRequestQueryKey(request.id),
      })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function clearDocument() {
    setDocument(null)
    setFileInputKey((key) => key + 1)
  }

  async function onDocument(file: File | undefined) {
    if (!file) {
      setDocument(null)
      return
    }
    const error = await assertIdentityPdf(file)
    if (error) {
      toast.error(error)
      clearDocument()
      return
    }
    setDocument(file)
  }

  return (
    <form
      className='space-y-3 rounded-lg border bg-background p-4'
      onSubmit={(event) => {
        event.preventDefault()
        if (!identityType || !document) {
          toast.error('Choose a document type and upload a PDF.')
          return
        }
        submitMutation.mutate()
      }}
    >
      <div className='space-y-2'>
        <Label htmlFor='resubmit-type'>Identity document</Label>
        <Select value={identityType || undefined} onValueChange={setIdentityType}>
          <SelectTrigger id='resubmit-type' className='w-full'>
            <SelectValue placeholder='Select a document type' />
          </SelectTrigger>
          <SelectContent>
            {IDENTITY_DOCUMENT_TYPES.map((item) => (
              <SelectItem key={item.value} value={item.value}>
                {item.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className='space-y-2'>
        <Label htmlFor='resubmit-file'>PDF attachment</Label>
        <Input
          key={fileInputKey}
          id='resubmit-file'
          type='file'
          accept='application/pdf,.pdf'
          onChange={(event) => void onDocument(event.target.files?.[0])}
        />
        <p className='text-xs text-muted-foreground'>
          PDF only, up to 2 MB.
          {document
            ? ` Selected: ${document.name} (${formatIdentityFileSize(document.size)}).`
            : ''}
        </p>
      </div>
      <Button type='submit' disabled={submitMutation.isPending}>
        {submitMutation.isPending ? 'Submitting…' : 'Submit new document'}
      </Button>
    </form>
  )
}
