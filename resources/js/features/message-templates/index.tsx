import { useState } from 'react'
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
import { analyzeSms } from '@/lib/sms'
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
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'
import { type MessageTemplate } from '@/types/message-templates'

type TemplateForm = {
  name: string
  body: string
}

const emptyForm: TemplateForm = {
  name: '',
  body: '',
}

export function MessageTemplatesPage() {
  const queryClient = useQueryClient()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<MessageTemplate | null>(null)
  const [form, setForm] = useState<TemplateForm>(emptyForm)
  const [deleteTarget, setDeleteTarget] = useState<MessageTemplate | null>(null)

  const templatesQuery = useQuery({
    queryKey: messageTemplatesQueryKey,
    queryFn: fetchMessageTemplates,
  })
  const templates = templatesQuery.data ?? []
  const analysis = analyzeSms(form.body)
  const dialogOpen = creating || editing !== null

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name: form.name.trim(), body: form.body }
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
      await queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
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
      await queryClient.invalidateQueries({ queryKey: messageTemplatesQueryKey })
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
    setForm({ name: template.name, body: template.body })
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

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Templates'
          description='Save messages you send often. Insert one when you send SMS.'
          actions={<Button onClick={openCreate}>New template</Button>}
        />

        {templatesQuery.isPending ? (
          <p className='text-sm text-muted-foreground'>Loading templates…</p>
        ) : null}

        {!templatesQuery.isPending && templates.length === 0 ? (
          <EmptyState
            icon={FileText}
            title='No templates yet'
            description='Save a message once, then insert it the next time you send SMS.'
            actionLabel='New template'
            onAction={openCreate}
          />
        ) : null}

        {templates.length > 0 ? (
          <div className='space-y-3'>
            {templates.map((template) => (
              <article
                key={template.id}
                className='rounded-xl border bg-card px-4 py-3'
              >
                <div className='flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
                  <div className='min-w-0'>
                    <h2 className='text-sm font-medium'>{template.name}</h2>
                    <p className='mt-1 line-clamp-2 text-sm text-muted-foreground'>
                      {template.body}
                    </p>
                    <p className='mt-2 text-xs text-muted-foreground'>
                      {formatNumber(template.characterCount)} characters ·{' '}
                      {formatNumber(template.segmentCount)} SMS
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
        ) : null}
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
              <Label htmlFor='template-body'>Message</Label>
              <Textarea
                id='template-body'
                className='min-h-36'
                value={form.body}
                maxLength={2000}
                placeholder='Type your message here...'
                onChange={(event) =>
                  setForm((current) => ({ ...current, body: event.target.value }))
                }
              />
              <div className='flex flex-wrap gap-4 text-sm text-muted-foreground'>
                <span>
                  Characters: {analysis.characterCount} / {analysis.singleLimit}
                </span>
                <span>Messages: {analysis.segmentCount} SMS</span>
              </div>
              {analysis.segmentCount > 1 ? (
                <p className='text-sm text-amber-700 dark:text-amber-400'>
                  This template uses {analysis.segmentCount} SMS for each person.
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
