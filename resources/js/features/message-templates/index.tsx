import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText } from 'lucide-react'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import { formatNumber } from '@/lib/format'
import {
  createMessageTemplate,
  deleteMessageTemplate,
  fetchMessageTemplates,
  messageTemplatesQueryKey,
  updateMessageTemplate,
} from '@/lib/message-templates'
import {
  analyzeSms,
  insertTextAtCursor,
  messageUsesName,
  NAME_EXAMPLE,
  NAME_PLACEHOLDER,
  renderNamedMessage,
} from '@/lib/sms'
import { Badge } from '@/components/ui/badge'
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
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { ListSkeleton } from '@/components/soscom/skeletons'
import { Main } from '@/components/layout/main'
import {
  type MessageTemplate,
  type MessageTemplateType,
  messageTemplateTypeLabels,
} from '@/types/message-templates'

type TemplateForm = {
  name: string
  type: MessageTemplateType
  body: string
}

const emptyForm: TemplateForm = {
  name: '',
  type: 'transactional',
  body: '',
}

const templateTypes = Object.keys(
  messageTemplateTypeLabels
) as MessageTemplateType[]

export function MessageTemplatesPage() {
  const queryClient = useQueryClient()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<MessageTemplate | null>(null)
  const [form, setForm] = useState<TemplateForm>(emptyForm)
  const [deleteTarget, setDeleteTarget] = useState<MessageTemplate | null>(null)
  const bodyRef = useRef<HTMLTextAreaElement>(null)

  const templatesQuery = useQuery({
    queryKey: messageTemplatesQueryKey,
    queryFn: fetchMessageTemplates,
  })
  const templates = templatesQuery.data ?? []
  const usesName = messageUsesName(form.body)
  const exampleBody = usesName
    ? renderNamedMessage(form.body, NAME_EXAMPLE)
    : form.body
  const analysis = analyzeSms(exampleBody)
  const dialogOpen = creating || editing !== null

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = {
        name: form.name.trim(),
        type: form.type,
        body: form.body,
      }
      if (editing) {
        return updateMessageTemplate(editing.id, payload)
      }

      return createMessageTemplate(payload)
    },
    onSuccess: async () => {
      toast.success(editing ? 'Template updated.' : 'Template saved.')
      setCreating(false)
      setEditing(null)
      setForm(emptyForm)
      void queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: () => {
      if (!deleteTarget) {
        throw new Error('No template selected.')
      }

      return deleteMessageTemplate(deleteTarget.id)
    },
    onSuccess: async () => {
      toast.success('Template deleted.')
      setDeleteTarget(null)
      void queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setCreating(true)
  }

  function openEdit(template: MessageTemplate) {
    setCreating(false)
    setEditing(template)
    setForm({
      name: template.name,
      type: template.type,
      body: template.body,
    })
  }

  function closeDialog(open: boolean) {
    if (open) {
      return
    }

    setCreating(false)
    setEditing(null)
    setForm(emptyForm)
  }

  function saveTemplate() {
    if (!form.name.trim()) {
      toast.error('Name this template before saving.')
      return
    }
    if (!form.body.trim()) {
      toast.error('Type a message before saving it as a template.')
      return
    }

    saveMutation.mutate()
  }

  function insertName() {
    const field = bodyRef.current
    const next = insertTextAtCursor(
      form.body,
      NAME_PLACEHOLDER,
      field?.selectionStart ?? null,
      field?.selectionEnd ?? null
    )
    if (next.value.length > 2000) {
      toast.error('The message is already at the 2,000 character limit.')
      return
    }

    setForm((current) => ({ ...current, body: next.value }))
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
          title='Templates'
          description='Save messages you send often. Type {name} where a person’s name should appear when you send.'
          actions={<Button onClick={openCreate}>New template</Button>}
        />

        {templatesQuery.isError ? (
          <QueryLoadError
            message="We couldn't load templates."
            onRetry={() => void templatesQuery.refetch()}
          />
        ) : templatesQuery.isPending ? (
          <ListSkeleton />
        ) : templates.length === 0 ? (
          <EmptyState
            icon={FileText}
            title='No templates yet'
            description='Save a message once, then insert it the next time you send SMS.'
            actionLabel='New template'
            onAction={openCreate}
          />
        ) : (
          <div className='space-y-3'>
            {templates.map((template) => (
              <article
                key={template.id}
                className='rounded-xl border bg-card px-4 py-3'
              >
                <div className='flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
                  <div className='min-w-0'>
                    <div className='flex flex-wrap items-center gap-2'>
                      <h2 className='text-sm font-medium'>{template.name}</h2>
                      <Badge variant='secondary' className='text-xs'>
                        {messageTemplateTypeLabels[template.type]}
                      </Badge>
                      {messageUsesName(template.body) ? (
                        <Badge variant='outline' className='text-xs'>
                          Uses {'{name}'}
                        </Badge>
                      ) : null}
                    </div>
                    <p className='mt-1 line-clamp-2 text-sm text-muted-foreground'>
                      {template.body}
                    </p>
                    <p className='mt-2 text-xs text-muted-foreground'>
                      {messageUsesName(template.body)
                        ? `About ${formatNumber(analyzeSms(renderNamedMessage(template.body, NAME_EXAMPLE)).segmentCount)} SMS with the name ${NAME_EXAMPLE}. A longer name can use more SMS.`
                        : `${formatNumber(template.characterCount)} characters · ${formatNumber(template.segmentCount)} SMS`}
                    </p>
                  </div>
                  <div className='flex shrink-0 gap-2'>
                    <Button
                      type='button'
                      size='sm'
                      variant='outline'
                      onClick={() => openEdit(template)}
                    >
                      Edit
                    </Button>
                    <Button
                      type='button'
                      size='sm'
                      variant='ghost'
                      onClick={() => setDeleteTarget(template)}
                    >
                      Delete
                    </Button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}
      </Main>

      <Dialog open={dialogOpen} onOpenChange={closeDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit template' : 'New template'}</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='template-name'>Name</Label>
              <Input
                id='template-name'
                value={form.name}
                maxLength={80}
                placeholder='Fee reminder'
                onChange={(event) =>
                  setForm((current) => ({ ...current, name: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='template-type'>Type</Label>
              <Select
                value={form.type}
                onValueChange={(value) =>
                  setForm((current) => ({
                    ...current,
                    type: value as MessageTemplateType,
                  }))
                }
              >
                <SelectTrigger id='template-type' className='w-full'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {templateTypes.map((type) => (
                    <SelectItem key={type} value={type}>
                      {messageTemplateTypeLabels[type]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='space-y-2'>
              <div className='flex items-center justify-between gap-3'>
                <Label htmlFor='template-body'>Message</Label>
                <Button
                  type='button'
                  size='sm'
                  variant='outline'
                  onClick={insertName}
                >
                  Insert {'{name}'}
                </Button>
              </div>
              <Textarea
                ref={bodyRef}
                id='template-body'
                className='min-h-36'
                value={form.body}
                maxLength={2000}
                placeholder='Hello {name}, your fee is due tomorrow.'
                onChange={(event) =>
                  setForm((current) => ({ ...current, body: event.target.value }))
                }
              />
              <p className='text-sm text-muted-foreground'>
                Use {'{name}'} exactly, with the brackets and no spaces. When you send, each person’s name replaces it.
              </p>
              <div className='flex flex-wrap gap-4 text-sm text-muted-foreground'>
                <span>
                  Characters: {analysis.characterCount} / {analysis.singleLimit}
                  {usesName ? ` for ${NAME_EXAMPLE}` : ''}
                </span>
                <span>
                  Messages: {analysis.segmentCount} SMS
                  {usesName ? ' for this example' : ''}
                </span>
              </div>
              {usesName ? (
                <div className='rounded-lg border bg-muted/40 px-3 py-3'>
                  <p className='text-xs font-medium text-muted-foreground'>
                    Example for {NAME_EXAMPLE}
                  </p>
                  <p className='mt-1 text-sm whitespace-pre-wrap'>{exampleBody}</p>
                  <p className='mt-2 text-xs text-muted-foreground'>
                    A longer name can use more SMS when you send.
                  </p>
                </div>
              ) : null}
              {analysis.segmentCount > 1 ? (
                <p className='text-sm text-amber-700 dark:text-amber-400'>
                  This template uses {analysis.segmentCount} SMS for each person
                  {usesName ? ` when the name is ${NAME_EXAMPLE}` : ''}.
                </p>
              ) : null}
            </div>
          </div>
          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              onClick={() => closeDialog(false)}
            >
              Cancel
            </Button>
            <Button
              type='button'
              disabled={saveMutation.isPending}
              onClick={saveTemplate}
            >
              {saveMutation.isPending ? 'Saving…' : 'Save template'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteTarget(null)
          }
        }}
        title='Delete this template?'
        desc='This removes it from your saved templates. Messages you already sent stay as they are.'
        cancelBtnText='Cancel'
        confirmText={deleteMutation.isPending ? 'Deleting…' : 'Delete'}
        destructive
        isLoading={deleteMutation.isPending}
        handleConfirm={() => deleteMutation.mutate()}
      />
    </>
  )
}
