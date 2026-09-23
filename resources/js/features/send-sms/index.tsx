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
import { formatNumber } from '@/lib/format'
import {
  createMessageTemplate,
  deleteMessageTemplate,
  fetchMessageTemplates,
  messageTemplatesQueryKey,
  updateMessageTemplate,
} from '@/lib/message-templates'
import { fetchUsableSenderIds, usableSenderIdsQueryKey } from '@/lib/sender-ids'
import { analyzeSms, parsePhoneNumbers } from '@/lib/sms'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
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
  const [uploadName, setUploadName] = useState('')
  const [message, setMessage] = useState('')
  const [templateName, setTemplateName] = useState('')
  const [editingTemplateId, setEditingTemplateId] = useState<number | null>(null)
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

  const payload = useMemo((): Omit<CampaignPayload, 'sender_id'> & {
    sender_id?: string
  } => {
    const base = {
      message,
      sender_id: senderId || undefined,
    }

    switch (mode) {
      case 'numbers':
        return { ...base, source: 'numbers', numbers: parsedNumbers }
      case 'group':
        return {
          ...base,
          source: 'group',
          group_id: groupId ? Number(groupId) : undefined,
        }
      case 'upload':
        return { ...base, source: 'upload', numbers: uploadNumbers }
      default: {
        const exhaustive: never = mode
        return exhaustive
      }
    }
  }, [
    groupId,
    message,
    mode,
    parsedNumbers,
    senderId,
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

  const smsBalance = useAuthStore((state) => state.user?.customer?.smsBalance ?? 0)
  const suspended = useAuthStore(
    (state) => state.user?.customer?.status === 'suspended'
  )
  const recipientCount = preview?.recipientCount ?? 0
  const segments = preview?.segmentsPerRecipient ?? analysis.segmentCount
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
      await queryClient.invalidateQueries({ queryKey: campaignsQueryKey })
      await queryClient.invalidateQueries({ queryKey: walletQueryKey })
      await queryClient.invalidateQueries({ queryKey: creditTransactionsQueryKey })
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
    if (recipientCount < 1) {
      toast.error('Add at least one valid Tanzanian number.')
      return
    }
    if (!message.trim()) {
      toast.error('Type a message before sending.')
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
      return
    }

    const rows = parseCsvText(await file.text())
    const phones = rows.flatMap((row) => row.map((cell) => cell.trim())).filter(Boolean)
    setUploadNumbers(phones)
  }

  const saveTemplateMutation = useMutation({
    mutationFn: (payload: { id: number | null; name: string; body: string }) => {
      if (payload.id === null) {
        return createMessageTemplate({ name: payload.name, body: payload.body })
      }

      return updateMessageTemplate(payload.id, {
        name: payload.name,
        body: payload.body,
      })
    },
    onSuccess: async (_template, payload) => {
      await queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
      toast.success(payload.id === null ? 'Template saved.' : 'Template updated.')
      setEditingTemplateId(null)
      setTemplateName('')
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const deleteTemplateMutation = useMutation({
    mutationFn: (id: number) => deleteMessageTemplate(id),
    onSuccess: async (_result, id) => {
      await queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
      setEditingTemplateId((current) => (current === id ? null : current))
      setTemplateName((current) =>
        editingTemplateId === id ? '' : current
      )
      toast.success('Template deleted.')
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function loadTemplate(template: MessageTemplate) {
    setMessage(template.body)
  }

  function beginEdit(template: MessageTemplate) {
    setEditingTemplateId(template.id)
    setTemplateName(template.name)
    setMessage(template.body)
  }

  function cancelEdit() {
    setEditingTemplateId(null)
    setTemplateName('')
  }

  function saveTemplate() {
    const name = templateName.trim()
    if (!name) {
      toast.error('Name this template before saving.')
      return
    }
    if (!message.trim()) {
      toast.error('Type a message before saving it as a template.')
      return
    }

    saveTemplateMutation.mutate({
      id: editingTemplateId,
      name,
      body: message,
    })
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Send SMS'
          description='Longer messages use more than 1 SMS per person. You will see the total before you send.'
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
              <Select
                value={senderId || undefined}
                onValueChange={setSenderId}
                disabled={senderIds.length === 0}
              >
                <SelectTrigger id='sender-id' className='mt-2'>
                  <SelectValue
                    placeholder={
                      senderIdsQuery.isPending
                        ? 'Loading Sender IDs…'
                        : 'No active Sender ID yet'
                    }
                  />
                </SelectTrigger>
                <SelectContent>
                  {senderIds.map((item) => (
                    <SelectItem key={item.id} value={item.name}>
                      {item.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
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
                    placeholder='0712445221, one number per line'
                    rows={6}
                  />
                </TabsContent>
                <TabsContent value='group'>
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
                      ? `${uploadName}: ${formatNumber(uploadNumbers.length)} values found.`
                      : 'Upload a CSV of Tanzanian numbers.'}
                  </p>
                </TabsContent>
              </Tabs>
            </section>

            <section className='rounded-xl border bg-card p-5'>
              <div className='flex items-center justify-between gap-3'>
                <Label>Message templates</Label>
                <p className='text-sm text-muted-foreground'>
                  {templatesQuery.isPending
                    ? 'Loading…'
                    : `${formatNumber(templates.length)} saved`}
                </p>
              </div>
              <div className='mt-3 max-h-64 space-y-2 overflow-y-auto'>
                {templates.map((template) => (
                  <div
                    key={template.id}
                    className={`flex flex-col gap-3 rounded-lg border px-3 py-2 sm:flex-row sm:items-start sm:justify-between ${
                      editingTemplateId === template.id ? 'border-primary' : ''
                    }`}
                  >
                    <div className='min-w-0 flex-1'>
                      <p className='text-sm font-medium'>{template.name}</p>
                      <p className='truncate text-xs text-muted-foreground'>
                        {template.body}
                      </p>
                      <p className='text-xs text-muted-foreground'>
                        {formatNumber(template.characterCount)} characters ·{' '}
                        {formatNumber(template.segmentCount)} SMS
                      </p>
                    </div>
                    <div className='flex shrink-0 gap-1'>
                      <Button
                        type='button'
                        size='sm'
                        variant='outline'
                        onClick={() => loadTemplate(template)}
                      >
                        Use
                      </Button>
                      <Button
                        type='button'
                        size='sm'
                        variant='ghost'
                        onClick={() => beginEdit(template)}
                      >
                        Edit
                      </Button>
                      <Button
                        type='button'
                        size='sm'
                        variant='ghost'
                        disabled={deleteTemplateMutation.isPending}
                        onClick={() => deleteTemplateMutation.mutate(template.id)}
                      >
                        Delete
                      </Button>
                    </div>
                  </div>
                ))}
                {!templatesQuery.isPending && templates.length === 0 ? (
                  <p className='text-sm text-muted-foreground'>
                    No templates yet. Type a message and save it to reuse later.
                  </p>
                ) : null}
              </div>

              <div className='mt-4 space-y-2'>
                <Label htmlFor='template-name'>Template name</Label>
                <Input
                  id='template-name'
                  value={templateName}
                  placeholder='Fee reminder'
                  maxLength={80}
                  onChange={(event) => setTemplateName(event.target.value)}
                />
              </div>

              <Label htmlFor='message' className='mt-4'>
                Message
              </Label>
              <Textarea
                id='message'
                className='mt-2 min-h-36'
                placeholder='Type your message here...'
                value={message}
                maxLength={2000}
                onChange={(event) => setMessage(event.target.value)}
              />
              <div className='mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground'>
                <span>
                  Characters: {analysis.characterCount} / {analysis.singleLimit}
                </span>
                <span>Messages: {analysis.segmentCount} SMS</span>
              </div>
              {analysis.segmentCount > 1 ? (
                <p className='mt-2 text-sm text-amber-700 dark:text-amber-400'>
                  This message uses {analysis.segmentCount} SMS for each person.
                </p>
              ) : null}
              <div className='mt-3 flex flex-wrap gap-2'>
                <Button
                  type='button'
                  variant='outline'
                  disabled={saveTemplateMutation.isPending}
                  onClick={saveTemplate}
                >
                  {saveTemplateMutation.isPending
                    ? 'Saving…'
                    : editingTemplateId === null
                      ? 'Save template'
                      : 'Update template'}
                </Button>
                {editingTemplateId !== null ? (
                  <Button type='button' variant='ghost' onClick={cancelEdit}>
                    Cancel
                  </Button>
                ) : null}
              </div>
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
                  <Input
                    id='schedule-at'
                    type='datetime-local'
                    value={scheduleAt}
                    onChange={(event) => setScheduleAt(event.target.value)}
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
              <Row label='SMS per person' value={String(segments || 0)} />
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

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={schedule ? 'Schedule this SMS?' : 'Send this SMS?'}
        desc={
          <div className='space-y-2 text-sm'>
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

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className='flex items-center justify-between gap-4'>
      <dt className='text-muted-foreground'>{label}</dt>
      <dd className='font-medium'>{value}</dd>
    </div>
  )
}
