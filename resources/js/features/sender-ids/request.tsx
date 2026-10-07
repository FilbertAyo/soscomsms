import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  createSenderIdRequest,
  senderIdRequestQueryKey,
  senderIdRequestsQueryKey,
} from '@/lib/sender-ids'
import { useAuthStore } from '@/stores/auth-store'
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
import { Textarea } from '@/components/ui/textarea'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'
import {
  IDENTITY_DOCUMENT_TYPES,
  SENDER_ID_USE_CASES,
} from '@/types/sender-ids'

const SAMPLE_LIMIT = 160
const PDF_LIMIT_BYTES = 2 * 1024 * 1024

const PURPOSE_HINT =
  'Transactional SMS is tied to something the person already did or expects, such as an OTP, receipt, delivery update, or appointment. Promotional SMS advertises an offer, product, or event and should go only to people who agreed to receive it. Choose both when this Sender ID will be used for both kinds of SMS.'

const SAMPLE_HINT =
  'Write one real example of an SMS you will send with this Sender ID, in 160 characters or fewer (one standard SMS). The operator uses this sample when approving the name. Use a made-up name, not a real customer’s details.'

export function RequestSenderId() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const businessName =
    useAuthStore((state) => state.user?.customer?.businessName) ?? ''
  const [name, setName] = useState('')
  const [purpose, setPurpose] = useState('')
  const [organization, setOrganization] = useState(businessName)
  const [website, setWebsite] = useState('')
  const [description, setDescription] = useState('')
  const [sample, setSample] = useState('')
  const [identityType, setIdentityType] = useState('')
  const [document, setDocument] = useState<File | null>(null)
  const [fileInputKey, setFileInputKey] = useState(0)

  const submitMutation = useMutation({
    mutationFn: () => {
      if (!document) {
        return Promise.reject(new Error('Upload a PDF of the selected identity document.'))
      }

      return createSenderIdRequest({
        name: name.trim(),
        purpose,
        organization: organization.trim(),
        websiteUrl: normalizeWebsite(website),
        description: description.trim() || undefined,
        sampleContent: sample.trim(),
        identityDocumentType: identityType,
        identityDocument: document,
      })
    },
    onSuccess: async (request) => {
      toast.success('Sender ID request submitted.')
      void queryClient.invalidateQueries({ queryKey: senderIdRequestsQueryKey })
      void queryClient.invalidateQueries({
        queryKey: senderIdRequestQueryKey(request.id),
      })
      await navigate({
        to: '/sender-ids/$senderId',
        params: { senderId: String(request.id) },
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

    const looksLikePdf =
      file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
    if (!looksLikePdf) {
      toast.error('Upload a PDF of the selected identity document.')
      clearDocument()
      return
    }

    if (file.size > PDF_LIMIT_BYTES) {
      toast.error('That PDF is larger than 2 MB. Use a 1–3 page scan.')
      clearDocument()
      return
    }

    const header = await file.slice(0, 5).text()
    if (!header.startsWith('%PDF')) {
      toast.error('Upload a PDF of the selected identity document.')
      clearDocument()
      return
    }

    setDocument(file)
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Request Sender ID'
          description='Ask to send SMS using your own business or organization name.'
        />
        <form
          className='max-w-xl space-y-5 rounded-xl border bg-card p-5'
          onSubmit={(event) => {
            event.preventDefault()
            if (
              !name.trim() ||
              !purpose ||
              !organization.trim() ||
              !sample.trim() ||
              !identityType ||
              !document
            ) {
              toast.error('Please complete the required fields.')
              return
            }
            if (website.trim() && !normalizeWebsite(website)) {
              toast.error('Enter a valid website, such as https://example.co.tz.')
              return
            }
            submitMutation.mutate()
          }}
        >
          <div className='space-y-2'>
            <Label htmlFor='sender-id'>
              Sender ID
              <RequiredMark />
            </Label>
            <Input
              id='sender-id'
              value={name}
              onChange={(event) =>
                setName(event.target.value.replaceAll(/\s+/g, ''))
              }
              maxLength={13}
              required
            />
            <p className='text-xs text-muted-foreground'>
              3 to 13 letters or numbers. Spaces are not allowed.
            </p>
          </div>
          <div className='space-y-2'>
            <div className='flex items-center gap-1.5'>
              <Label htmlFor='purpose'>
                Purpose
                <RequiredMark />
              </Label>
              <FieldHint label='About purpose' hint={PURPOSE_HINT} />
            </div>
            <Select value={purpose || undefined} onValueChange={setPurpose}>
              <SelectTrigger id='purpose' className='w-full'>
                <SelectValue placeholder='Select a purpose' />
              </SelectTrigger>
              <SelectContent>
                {SENDER_ID_USE_CASES.map((item) => (
                  <SelectItem key={item.value} value={item.value}>
                    {item.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className='space-y-2'>
            <Label htmlFor='org'>
              Business / Organization Name
              <RequiredMark />
            </Label>
            <Input
              id='org'
              value={organization}
              onChange={(event) => setOrganization(event.target.value)}
              required
            />
          </div>
          <div className='space-y-2'>
            <Label htmlFor='website'>Website</Label>
            <Input
              id='website'
              type='text'
              inputMode='url'
              autoComplete='url'
              value={website}
              onChange={(event) => setWebsite(event.target.value)}
              placeholder='https://example.co.tz'
            />
            <p className='text-xs text-muted-foreground'>
              Optional. Your company or organization website.
            </p>
          </div>
          <div className='space-y-2'>
            <Label htmlFor='description'>Description</Label>
            <Textarea
              id='description'
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </div>
          <div className='space-y-2'>
            <div className='flex items-center justify-between gap-3'>
              <div className='flex items-center gap-1.5'>
                <Label htmlFor='sample'>
                  Sample content
                  <RequiredMark />
                </Label>
                <FieldHint label='About sample content' hint={SAMPLE_HINT} />
              </div>
              <p className='text-xs text-muted-foreground tabular-nums'>
                {sample.length} / {SAMPLE_LIMIT}
              </p>
            </div>
            <Textarea
              id='sample'
              value={sample}
              maxLength={SAMPLE_LIMIT}
              onChange={(event) =>
                setSample(event.target.value.slice(0, SAMPLE_LIMIT))
              }
              placeholder=''
              required
            />
          </div>
          <div className='space-y-2'>
            <Label htmlFor='identity-type'>
              Identity document
              <RequiredMark />
            </Label>
            <Select
              value={identityType || undefined}
              onValueChange={(value) => {
                setIdentityType(value)
                clearDocument()
              }}
            >
              <SelectTrigger id='identity-type' className='w-full'>
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
            {identityType ? (
              <div className='space-y-2 pt-1'>
                <Label htmlFor='identity-file'>
                  PDF attachment
                  <RequiredMark />
                </Label>
                <Input
                  key={fileInputKey}
                  id='identity-file'
                  type='file'
                  accept='application/pdf,.pdf'
                  onChange={(event) =>
                    void onDocument(event.target.files?.[0])
                  }
                />
                <p className='text-xs text-muted-foreground'>
                  PDF only, up to 2 MB. 
                  {document
                    ? ` Selected: ${document.name} (${formatFileSize(document.size)}).`
                    : ''}
                </p>
              </div>
            ) : (
              <p className='text-xs text-muted-foreground'>
                Choose the document, then upload a PDF of it.
              </p>
            )}
          </div>
          <p className='text-sm text-muted-foreground'>
            Sender IDs usually require approval before they can be used.
          </p>
          <Button type='submit' disabled={submitMutation.isPending}>
            {submitMutation.isPending ? 'Submitting…' : 'Submit Request'}
          </Button>
        </form>
      </Main>
    </>
  )
}

function RequiredMark() {
  return (
    <span className='-ms-1 text-destructive' aria-hidden='true'>
      *
    </span>
  )
}

function FieldHint({ label, hint }: { label: string; hint: string }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button
          type='button'
          className='inline-flex size-4 items-center justify-center rounded-full border border-muted-foreground/50 text-[10px] font-semibold leading-none text-muted-foreground'
          aria-label={label}
        >
          !
        </button>
      </TooltipTrigger>
      <TooltipContent className='max-w-xs text-start text-balance'>
        {hint}
      </TooltipContent>
    </Tooltip>
  )
}

function normalizeWebsite(value: string): string | undefined {
  const trimmed = value.trim()
  if (!trimmed) {
    return undefined
  }

  const withScheme = /^https?:\/\//i.test(trimmed)
    ? trimmed
    : `https://${trimmed}`

  try {
    const url = new URL(withScheme)
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      return undefined
    }
  } catch {
    return undefined
  }

  return withScheme
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`
  }

  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
