import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate } from '@tanstack/react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  campaignsQueryKey,
  createCampaign,
  previewCampaign,
} from '@/lib/campaigns'
import { contactGroupsQueryKey, fetchContactGroups } from '@/lib/contacts'
import {
  creditTransactionsQueryKey,
  walletQueryKey,
  withSmsBalance,
} from '@/lib/credits'
import { parseCsvText } from '@/lib/csv'
import { formatNumber, formatTzMobile } from '@/lib/format'
import {
  fetchMessageTemplates,
  messageTemplatesQueryKey,
} from '@/lib/message-templates'
import { fetchUsableSenderIds, usableSenderIdsQueryKey } from '@/lib/sender-ids'
import {
  analyzeSms,
  insertTextAtCursor,
  messageUsesName,
  NAME_EXAMPLE,
  NAME_PLACEHOLDER,
  parseNamedRecipients,
  parsePhoneNumbers,
  parseUploadRecipients,
  renderNamedMessage,
  type NamedRecipient,
} from '@/lib/sms'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { DateTimePicker } from '@/components/date-time-picker'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { ListSkeleton } from '@/components/soscom/skeletons'
import { Main } from '@/components/layout/main'
import { type CampaignPayload } from '@/types/campaigns'
import { type MessageTemplate } from '@/types/message-templates'

type RecipientMode = 'numbers' | 'group' | 'upload'

export function SendSms() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [senderId, setSenderId] = useState('')
  const [mode, setMode] = useState<RecipientMode>('numbers')
  const [numbers, setNumbers] = useState('')
  const [groupId, setGroupId] = useState('')
  const [uploadNumbers, setUploadNumbers] = useState<string[]>([])
  const [uploadNamed, setUploadNamed] = useState<NamedRecipient[]>([])
  const [uploadName, setUploadName] = useState('')
  const [message, setMessage] = useState('')
  const [personalize, setPersonalize] = useState(false)
  const [templatePickerOpen, setTemplatePickerOpen] = useState(false)
  const messageRef = useRef<HTMLTextAreaElement>(null)
  const [schedule, setSchedule] = useState(false)
  const [scheduleAt, setScheduleAt] = useState('')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const scheduleIntentRef = useRef(false)

  const senderIdsQuery = useQuery({
    queryKey: usableSenderIdsQueryKey,
    queryFn: fetchUsableSenderIds,
  })
  const groupsQuery = useQuery({
    queryKey: contactGroupsQueryKey,
    queryFn: fetchContactGroups,
  })
  const templatesQuery = useQuery({
    queryKey: messageTemplatesQueryKey,
    queryFn: fetchMessageTemplates,
    enabled: templatePickerOpen,
  })

  const senderIds = senderIdsQuery.data ?? []
  const groups = groupsQuery.data ?? []
  const templates = templatesQuery.data ?? []

  useEffect(() => {
    if (senderIds.length === 0) {
      return
    }
    if (!senderIds.some((item) => item.name === senderId)) {
      setSenderId(senderIds[0]?.name ?? '')
    }
  }, [senderId, senderIds])

  useEffect(() => {
    if (groups.length === 0) {
      return
    }
    if (!groups.some((item) => String(item.id) === groupId)) {
      setGroupId(String(groups[0]?.id ?? ''))
    }
  }, [groupId, groups])

  const parsedNumbers = useMemo(() => parsePhoneNumbers(numbers), [numbers])
  const namedFromNumbers = useMemo(() => parseNamedRecipients(numbers), [numbers])
  const namedDraft =
    mode === 'numbers' ? namedFromNumbers : mode === 'upload' ? uploadNamed : []
  const localFirstNamed = namedDraft.find((person) => person.name !== '')

  const payload = useMemo((): Omit<CampaignPayload, 'sender_id'> & {
    sender_id?: string
  } => {
    const base = {
      message,
      sender_id: senderId || undefined,
      personalize,
    }

    switch (mode) {
      case 'numbers':
        return personalize
          ? { ...base, source: 'numbers', named_recipients: namedFromNumbers }
          : { ...base, source: 'numbers', numbers: parsedNumbers }
      case 'group':
        return {
          ...base,
          source: 'group',
          group_id: groupId ? Number(groupId) : undefined,
        }
      case 'upload':
        return personalize
          ? { ...base, source: 'upload', named_recipients: uploadNamed }
          : { ...base, source: 'upload', numbers: uploadNumbers }
      default: {
        const exhaustive: never = mode
        return exhaustive
      }
    }
  }, [
    groupId,
    message,
    mode,
    namedFromNumbers,
    parsedNumbers,
    personalize,
    senderId,
    uploadNamed,
    uploadNumbers,
  ])

  const [debouncedPayload, setDebouncedPayload] = useState(payload)

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedPayload(payload)
    }, 400)

    return () => window.clearTimeout(timer)
  }, [payload])

  const previewQuery = useQuery({
    queryKey: ['sms-preview', debouncedPayload],
    queryFn: () => previewCampaign(debouncedPayload),
    enabled:
      message.trim().length > 0 && (mode !== 'group' || Boolean(groupId)),
  })
  const preview = previewQuery.data
  const analysis = analyzeSms(message)
  const previewIsCurrent =
    previewQuery.isSuccess &&
    !previewQuery.isFetching &&
    JSON.stringify(payload) === JSON.stringify(debouncedPayload)
  const usesName = messageUsesName(message)
  const exampleName =
    (previewIsCurrent ? preview?.example?.name : null) ||
    localFirstNamed?.name ||
    NAME_EXAMPLE
  const exampleMessage =
    personalize && usesName
      ? (previewIsCurrent ? preview?.example?.message : null) ||
        renderNamedMessage(message, exampleName)
      : message
  const shownAnalysis =
    personalize && usesName ? analyzeSms(exampleMessage) : analysis

  const smsBalance = useAuthStore((state) => state.user?.customer?.smsBalance ?? 0)
  const suspended = useAuthStore(
    (state) => state.user?.customer?.status === 'suspended'
  )
  const recipientCount = preview?.recipientCount ?? 0
  const segments = preview?.segmentsPerRecipient ?? shownAnalysis.segmentCount
  const perPerson =
    preview && preview.personalized && preview.segmentsMin !== preview.segmentsMax
      ? `${preview.segmentsMin}–${preview.segmentsMax}`
      : String(segments || 0)
  const totalSms = preview?.totalSms ?? recipientCount * segments
  const balanceAfter = preview?.balanceAfter ?? smsBalance - totalSms
  const activeSender = senderIds.find((item) => item.name === senderId)

  const sendMutation = useMutation({
    mutationFn: () => {
      if (!senderId) {
        throw new Error('Please select a Sender ID.')
      }

      return createCampaign({
        ...(payload as CampaignPayload),
        sender_id: senderId,
        scheduled_at:
          scheduleIntentRef.current && scheduleAt ? scheduleAt : undefined,
      })
    },
    onSuccess: async (result) => {
      const current = useAuthStore.getState().user
      if (current) {
        useAuthStore.getState().setUser(withSmsBalance(current, result.smsBalance))
      }
      void queryClient.invalidateQueries({ queryKey: campaignsQueryKey })
      void queryClient.invalidateQueries({ queryKey: walletQueryKey })
      void queryClient.invalidateQueries({ queryKey: creditTransactionsQueryKey })
      toast.success(
        result.campaign.status === 'scheduled'
          ? 'Your SMS is scheduled. We will send it at the time you chose.'
          : result.campaign.status === 'completed' ||
              result.campaign.status === 'partially_delivered'
            ? 'Your SMS has been sent. Check the campaign page for delivery status.'
            : 'Your SMS is being sent. Check the campaign page for delivery status.'
      )
      setConfirmOpen(false)
      await navigate({
        to: '/campaigns/$campaignId',
        params: { campaignId: String(result.campaign.id) },
      })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function openConfirm(willSchedule: boolean) {
    scheduleIntentRef.current = willSchedule
    setSchedule(willSchedule)

    if (suspended) {
      toast.error('This account is paused. You cannot send SMS right now.')
      return
    }
    if (!senderId) {
      toast.error('Please select a Sender ID.')
      return
    }
    if (!message.trim()) {
      toast.error('Type a message before sending.')
      return
    }
    if (mode === 'group' && !groupId) {
      toast.error('Choose a contact group.')
      return
    }
    if (!previewIsCurrent || !preview) {
      toast.error('Still checking this SMS. Wait a moment, then try again.')
      return
    }
    if (personalize && !preview.placeholderReady) {
      toast.error(
        'Add {name} where each person’s name should appear, or turn names off.'
      )
      return
    }
    if (personalize && preview.missingNameCount > 0) {
      toast.error(
        'Every person needs a name. Use one line per person: 0712445221, Amina.'
      )
      return
    }
    if (personalize && preview.overLimitCount > 0) {
      toast.error(
        'A name makes the message longer than 2,000 characters. Shorten the message or the name.'
      )
      return
    }
    if (recipientCount < 1) {
      toast.error('Add at least one valid Tanzanian number.')
      return
    }
    if (willSchedule && !scheduleAt) {
      toast.error('Choose a date and time to schedule this SMS.')
      return
    }
    if (preview && !preview.enoughCredits) {
      toast.error('Not enough SMS. Buy more before sending.')
      return
    }
    setConfirmOpen(true)
  }

  async function onUpload(file: File | null) {
    setUploadName(file?.name ?? '')
    if (!file) {
      setUploadNumbers([])
      setUploadNamed([])
      return
    }

    const rows = parseCsvText(await file.text())
    const phones = rows.flatMap((row) => row.map((cell) => cell.trim())).filter(Boolean)
    setUploadNumbers(phones)
    setUploadNamed(parseUploadRecipients(rows))
  }

  function insertTemplate(template: MessageTemplate) {
    setMessage(template.body)
    if (messageUsesName(template.body)) {
      setPersonalize(true)
      toast('This template includes {name}. Add a name for each person.')
    }
    setTemplatePickerOpen(false)
  }

  function insertName() {
    const field = messageRef.current
    const next = insertTextAtCursor(
      message,
      NAME_PLACEHOLDER,
      field?.selectionStart ?? null,
      field?.selectionEnd ?? null
    )
    if (next.value.length > 2000) {
      toast.error('The message is already at the 2,000 character limit.')
      return
    }

    setPersonalize(true)
    setMessage(next.value)
    window.requestAnimationFrame(() => {
      field?.focus()
      field?.setSelectionRange(next.caret, next.caret)
    })
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Send SMS'
          description='Send the same SMS to everyone, or turn on names so each person gets their own. You will see a preview before it sends.'
        />

        {suspended ? (
          <p className='mb-6 rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive'>
            This account is paused. You cannot send SMS until Soscom turns it back on.
          </p>
        ) : null}

        <div className='grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]'>
          <div className='space-y-6'>
            <section className='rounded-xl border bg-card p-5'>
              <div className='flex items-center justify-between gap-3'>
                <Label htmlFor='sender-id'>Sender ID</Label>
                <Link
                  to='/sender-ids/request'
                  className='text-sm text-primary hover:underline'
                >
                  + Request new Sender ID
                </Link>
              </div>
              {senderIdsQuery.isPending ? (
                <Skeleton className='mt-2 h-9 w-full' />
              ) : senderIdsQuery.isError ? (
                <QueryLoadError
                  className='mt-2'
                  message="We couldn't load Sender IDs."
                  onRetry={() => void senderIdsQuery.refetch()}
                />
              ) : (
                <Select
                  value={senderId || undefined}
                  onValueChange={setSenderId}
                  disabled={senderIds.length === 0}
                >
                  <SelectTrigger id='sender-id' className='mt-2'>
                    <SelectValue placeholder='No active Sender ID yet' />
                  </SelectTrigger>
                  <SelectContent>
                    {senderIds.map((item) => (
                      <SelectItem key={item.id} value={item.name}>
                        {item.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </section>

            <section className='rounded-xl border bg-card p-5'>
              <div className='mb-3 flex items-center justify-between'>
                <Label>Add recipients</Label>
                <p className='text-sm font-medium'>
                  {formatNumber(recipientCount)} valid
                  {preview && preview.invalidCount > 0
                    ? ` · ${formatNumber(preview.invalidCount)} invalid`
                    : ''}
                </p>
              </div>
              <div className='mb-4 flex items-start justify-between gap-4 rounded-lg border bg-muted/40 px-3 py-3'>
                <div>
                  <Label htmlFor='use-names'>Use each person’s name</Label>
                  <p className='mt-1 text-sm font-normal text-muted-foreground'>
                    {personalize
                      ? 'Write {name} in the message. Each person gets their own name there. Turn this off to send the same SMS to everyone.'
                      : 'Off: everyone receives the same message. Turn this on when each SMS should include that person’s name.'}
                  </p>
                </div>
                <Switch
                  id='use-names'
                  className='mt-0.5'
                  checked={personalize}
                  onCheckedChange={setPersonalize}
                />
              </div>
              <Tabs
                value={mode}
                onValueChange={(value) => setMode(value as RecipientMode)}
              >
                <TabsList className='mb-4 h-auto w-full flex-wrap'>
                  <TabsTrigger value='numbers'>Enter numbers</TabsTrigger>
                  <TabsTrigger value='group'>Select group</TabsTrigger>
                  <TabsTrigger value='upload'>Upload CSV</TabsTrigger>
                </TabsList>
                <TabsContent value='numbers'>
                  <Textarea
                    value={numbers}
                    onChange={(event) => setNumbers(event.target.value)}
                    placeholder={
                      personalize
                        ? '0712445221, Amina\n0754221891, Juma Hassan'
                        : '0712445221, one number per line'
                    }
                    rows={6}
                  />
                </TabsContent>
                <TabsContent value='group'>
                  {groupsQuery.isError ? (
                    <QueryLoadError
                      message="We couldn't load contact groups."
                      onRetry={() => void groupsQuery.refetch()}
                    />
                  ) : (
                    <Select
                      value={groupId || undefined}
                      onValueChange={setGroupId}
                      disabled={groups.length === 0}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder='Select contact group' />
                      </SelectTrigger>
                      <SelectContent>
                        {groups.map((group) => (
                          <SelectItem key={group.id} value={String(group.id)}>
                            {group.name} ({formatNumber(group.contactsCount)})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </TabsContent>
                <TabsContent value='upload'>
                  <Input
                    type='file'
                    accept='.csv,.txt,text/csv'
                    onChange={(event) =>
                      void onUpload(event.target.files?.[0] ?? null)
                    }
                  />
                  <p className='mt-2 text-sm text-muted-foreground'>
                    {uploadName
                      ? personalize
                        ? `${uploadName}: ${formatNumber(uploadNamed.length)} rows found.`
                        : `${uploadName}: ${formatNumber(uploadNumbers.length)} values found.`
                      : personalize
                        ? 'Upload a CSV with two columns: number, then name. A header row like phone,name is fine.'
                        : 'Upload a CSV of Tanzanian numbers.'}
                  </p>
                </TabsContent>
              </Tabs>
              {personalize && mode === 'group' ? (
                <p className='mt-3 text-sm text-muted-foreground'>
                  Each contact’s saved name replaces {'{name}'}. A contact without a name is stopped before sending.
                </p>
              ) : null}
              {personalize && mode === 'group' && (preview?.missingNameCount ?? 0) > 0 ? (
                <p className='mt-2 text-sm text-amber-700 dark:text-amber-400'>
                  {formatNumber(preview?.missingNameCount ?? 0)}{' '}
                  {preview?.missingNameCount === 1 ? 'contact has' : 'contacts have'} no name. Add the name in Contacts, then come back.
                </p>
              ) : null}
              {personalize && mode !== 'group' ? (
                <PeoplePreview
                  people={namedDraft}
                  emptyTip={
                    mode === 'numbers'
                      ? 'One person per line: number, then name. Example: 0712445221, Amina'
                      : null
                  }
                />
              ) : null}
            </section>

            <section className='rounded-xl border bg-card p-5'>
              <div className='flex flex-wrap items-center justify-between gap-3'>
                <Label htmlFor='message'>Message</Label>
                <div className='flex flex-wrap gap-2'>
                  <Button
                    type='button'
                    size='sm'
                    variant='outline'
                    onClick={insertName}
                  >
                    Insert {'{name}'}
                  </Button>
                  <Button
                    type='button'
                    size='sm'
                    variant='outline'
                    onClick={() => setTemplatePickerOpen(true)}
                  >
                    Insert template
                  </Button>
                </div>
              </div>
              <Textarea
                ref={messageRef}
                id='message'
                className='mt-2 min-h-36'
                placeholder={
                  personalize
                    ? 'Hello {name}, your fee is due tomorrow.'
                    : 'Type your message here...'
                }
                value={message}
                maxLength={2000}
                onChange={(event) => setMessage(event.target.value)}
              />
              <div className='mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground'>
                <span>
                  Characters: {shownAnalysis.characterCount} / {shownAnalysis.singleLimit}
                  {personalize && usesName ? ` for ${exampleName}` : ''}
                </span>
                <span>
                  SMS per person: {shownAnalysis.segmentCount}
                  {personalize && usesName ? ' for this example' : ''}
                </span>
              </div>
              {shownAnalysis.segmentCount > 1 ? (
                <p className='mt-2 text-sm text-amber-700 dark:text-amber-400'>
                  {personalize && usesName
                    ? `This example uses ${shownAnalysis.segmentCount} SMS for each person.`
                    : `This message uses ${shownAnalysis.segmentCount} SMS for each person.`}
                </p>
              ) : null}
              {personalize && message.trim() !== '' && !usesName ? (
                <p className='mt-2 text-sm text-amber-700 dark:text-amber-400'>
                  Add {'{name}'} where the name should appear, or turn names off to send this same text to everyone.
                </p>
              ) : null}
              {!personalize && usesName ? (
                <div className='mt-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-3 text-sm'>
                  <p>
                    This message contains {'{name}'}. If you send now, people receive those brackets as text.
                  </p>
                  <Button
                    type='button'
                    size='sm'
                    variant='outline'
                    className='mt-2'
                    onClick={() => setPersonalize(true)}
                  >
                    Use each person’s name
                  </Button>
                </div>
              ) : null}
            </section>

            <section className='rounded-xl border bg-card p-5'>
              <div className='flex flex-col gap-3 sm:flex-row'>
                <Button
                  className='flex-1'
                  variant={schedule ? 'outline' : 'default'}
                  disabled={suspended}
                  onClick={() => openConfirm(false)}
                >
                  Send Now
                </Button>
                <Button
                  className='flex-1'
                  variant={schedule ? 'default' : 'outline'}
                  disabled={suspended}
                  onClick={() => setSchedule(true)}
                >
                  Schedule
                </Button>
              </div>
              {schedule ? (
                <div className='mt-4 space-y-2'>
                  <Label htmlFor='schedule-at'>Send date and time (Tanzania time)</Label>
                  <DateTimePicker
                    id='schedule-at'
                    value={scheduleAt}
                    onChange={setScheduleAt}
                    placeholder='Pick date and time'
                  />
                  <p className='text-xs text-muted-foreground'>
                    We will send at this time. SMS is taken from your balance
                    when you confirm.
                  </p>
                  <Button
                    className='w-full'
                    disabled={suspended}
                    onClick={() => openConfirm(true)}
                  >
                    Schedule SMS
                  </Button>
                </div>
              ) : null}
            </section>
          </div>

          <aside className='h-fit rounded-xl border bg-card p-5 xl:sticky xl:top-24'>
            <h2 className='font-semibold'>Summary</h2>
            <dl className='mt-4 space-y-3 text-sm'>
              <Row label='Recipients' value={formatNumber(recipientCount)} />
              <Row label='SMS per person' value={perPerson} />
              <Row label='Total SMS' value={formatNumber(totalSms)} />
              <Row
                label='Balance after sending'
                value={`${formatNumber(Math.max(balanceAfter, 0))} SMS`}
              />
            </dl>
            <p className='mt-4 text-xs text-muted-foreground'>
              Current SMS Balance: {formatNumber(smsBalance)} SMS
            </p>
            {preview && !preview.enoughCredits && totalSms > 0 ? (
              <p className='mt-3 text-sm text-destructive'>
                Not enough SMS. Buy more before sending.
              </p>
            ) : null}
          </aside>
        </div>
      </Main>

      <Dialog open={templatePickerOpen} onOpenChange={setTemplatePickerOpen}>
        <DialogContent className='sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>Insert template</DialogTitle>
            <DialogDescription>
              Choose a saved template. You can still change the text before sending.
            </DialogDescription>
          </DialogHeader>
          <div className='max-h-80 space-y-2 overflow-y-auto'>
            {templatesQuery.isError ? (
              <QueryLoadError
                message="We couldn't load templates."
                onRetry={() => void templatesQuery.refetch()}
              />
            ) : templatesQuery.isPending ? (
              <ListSkeleton rows={3} />
            ) : templates.length === 0 ? (
              <p className='text-sm text-muted-foreground'>
                No templates yet.{' '}
                <Link to='/templates' className='text-primary hover:underline'>
                  Create one
                </Link>
              </p>
            ) : (
              templates.map((template) => (
                <button
                  key={template.id}
                  type='button'
                  className='w-full rounded-lg border px-3 py-2 text-left hover:bg-accent'
                  onClick={() => insertTemplate(template)}
                >
                  <span className='flex flex-wrap items-center gap-2 text-sm font-medium'>
                    {template.name}
                    {messageUsesName(template.body) ? (
                      <span className='rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground'>
                        Uses {'{name}'}
                      </span>
                    ) : null}
                  </span>
                  <span className='mt-1 block truncate text-xs text-muted-foreground'>
                    {template.body}
                  </span>
                  <span className='mt-1 block text-xs text-muted-foreground'>
                    {formatNumber(template.characterCount)} characters ·{' '}
                    {formatNumber(template.segmentCount)} SMS
                  </span>
                </button>
              ))
            )}
          </div>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        className='sm:max-w-lg'
        title={schedule ? 'Schedule this SMS?' : 'Send this SMS?'}
        desc={
          <div className='space-y-3 text-sm'>
            {preview?.example ? (
              <div className='rounded-lg border bg-muted/50 px-3 py-2'>
                <p className='text-xs font-medium text-muted-foreground'>
                  {preview.example.name
                    ? `Example for ${preview.example.name} (${formatTzMobile(preview.example.phone)})`
                    : `Example for ${formatTzMobile(preview.example.phone)}`}
                </p>
                <p className='mt-1 text-foreground whitespace-pre-wrap'>
                  {preview.example.message}
                </p>
              </div>
            ) : (
              <p className='text-foreground whitespace-pre-wrap'>{message}</p>
            )}
            {personalize && preview?.example?.name ? (
              <p className='text-muted-foreground'>
                This is the first person. Everyone else gets their own name in place of {'{name}'}.
              </p>
            ) : (
              <p className='text-muted-foreground'>
                Everyone receives this same message.
              </p>
            )}
            {!personalize && usesName ? (
              <p className='text-amber-700 dark:text-amber-400'>
                {'{name}'} will be sent as text. Turn on “Use each person’s name” if you want a real name instead.
              </p>
            ) : null}
            <p>
              {formatNumber(totalSms)} SMS from{' '}
              <span className='text-foreground font-medium'>
                {activeSender?.name ?? senderId}
              </span>
              .
            </p>
            <p className='text-muted-foreground'>
              {schedule
                ? 'We will take the SMS from your balance now and send at the time you chose.'
                : 'We will take the SMS from your balance and start sending now.'}
            </p>
          </div>
        }
        cancelBtnText='Cancel'
        confirmText={
          sendMutation.isPending
            ? 'Please wait…'
            : schedule
              ? 'Confirm & Schedule'
              : 'Confirm & Send'
        }
        isLoading={sendMutation.isPending}
        handleConfirm={() => sendMutation.mutate()}
      />
    </>
  )
}

function PeoplePreview({
  people,
  emptyTip = null,
}: {
  people: NamedRecipient[]
  emptyTip?: string | null
}) {
  if (people.length === 0) {
    return emptyTip ? (
      <p className='mt-3 text-sm text-muted-foreground'>{emptyTip}</p>
    ) : null
  }

  const shown = people.slice(0, 3)
  const missing = people.filter((person) => person.name === '').length

  return (
    <div className='mt-3 rounded-lg border px-3 py-2'>
      <p className='text-xs font-medium text-muted-foreground'>How we read this</p>
      <ul className='mt-2 space-y-1 text-sm'>
        {shown.map((person, index) => (
          <li
            key={`${person.phone}-${index}`}
            className='flex items-center justify-between gap-3'
          >
            <span
              className={
                person.name
                  ? 'truncate'
                  : 'text-amber-700 dark:text-amber-400'
              }
            >
              {person.name || 'Name missing'}
            </span>
            <span className='shrink-0 text-muted-foreground'>{person.phone}</span>
          </li>
        ))}
      </ul>
      {people.length > shown.length ? (
        <p className='mt-2 text-xs text-muted-foreground'>
          and {formatNumber(people.length - shown.length)} more
        </p>
      ) : null}
      {missing > 0 ? (
        <p className='mt-2 text-sm text-amber-700 dark:text-amber-400'>
          {formatNumber(missing)}{' '}
          {missing === 1 ? 'person still needs' : 'people still need'} a name.
        </p>
      ) : null}
    </div>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className='flex items-center justify-between gap-4'>
      <dt className='text-muted-foreground'>{label}</dt>
      <dd className='font-medium'>{value}</dd>
    </div>
  )
}
