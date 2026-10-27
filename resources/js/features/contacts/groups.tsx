import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Users } from 'lucide-react'
import { applyValidationErrors } from '@/lib/auth'
import {
  contactGroupsQueryKey,
  contactsQueryKey,
  createContactGroup,
  deleteContactGroup,
  fetchContactGroups,
  updateContactGroup,
} from '@/lib/contacts'
import { formatNumber } from '@/lib/format'
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
import { QueryLoadError } from '@/components/soscom/query-load-error'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { CardSkeletonGrid } from '@/components/soscom/skeletons'
import { Main } from '@/components/layout/main'
import { type ContactGroup } from '@/types/contacts'

type GroupForm = {
  name: string
  description: string
}

const emptyForm: GroupForm = { name: '', description: '' }

export function ContactGroupsPage() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<GroupForm>(emptyForm)
  const [editing, setEditing] = useState<ContactGroup | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<ContactGroup | null>(null)

  const groupsQuery = useQuery({
    queryKey: contactGroupsQueryKey,
    queryFn: fetchContactGroups,
  })
  const groups = groupsQuery.data ?? []

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim() || null,
      }

      if (editing) {
        return updateContactGroup(editing.id, payload)
      }

      return createContactGroup({
        name: payload.name,
        description: payload.description ?? undefined,
      })
    },
    onSuccess: async (group) => {
      toast.success(editing ? 'Group updated.' : 'Group created.')
      setCreating(false)
      setEditing(null)
      setForm(emptyForm)
      void queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })
      void queryClient.invalidateQueries({ queryKey: ['contacts'] })
      return group
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: () => deleteContactGroup(deleting?.id ?? 0),
    onSuccess: async () => {
      toast.success('Group deleted. Contacts were kept.')
      setDeleting(null)
      void queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })
      void queryClient.invalidateQueries({ queryKey: ['contacts'] })
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

  function openEdit(group: ContactGroup) {
    setCreating(false)
    setEditing(group)
    setForm({
      name: group.name,
      description: group.description ?? '',
    })
  }

  const dialogOpen = creating || editing !== null

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Groups'
          description='Organize contacts so you can message many people at once.'
          actions={<Button onClick={openCreate}>Create Group</Button>}
        />
        {groupsQuery.isError ? (
          <QueryLoadError
            message="We couldn't load groups."
            onRetry={() => void groupsQuery.refetch()}
          />
        ) : groupsQuery.isPending ? (
          <CardSkeletonGrid />
        ) : groups.length === 0 ? (
          <EmptyState
            icon={Users}
            title='No groups yet'
            description='Create a group such as Parents, then add contacts to it.'
            actionLabel='Create Group'
            onAction={openCreate}
          />
        ) : (
          <div className='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
            {groups.map((group) => (
              <div key={group.id} className='rounded-xl border bg-card p-5'>
                <h2 className='font-semibold'>{group.name}</h2>
                <p className='mt-1 text-sm text-muted-foreground'>
                  {group.description || 'No description'}
                </p>
                <p className='mt-4 text-sm'>
                  {formatNumber(group.contactsCount)} contacts
                </p>
                <div className='mt-4 flex gap-2'>
                  <Button
                    size='sm'
                    variant='outline'
                    onClick={() => openEdit(group)}
                  >
                    Rename
                  </Button>
                  <Button
                    size='sm'
                    variant='ghost'
                    onClick={() => setDeleting(group)}
                  >
                    Delete
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </Main>
      <Dialog
        open={dialogOpen}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false)
            setEditing(null)
            setForm(emptyForm)
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Rename Group' : 'Create Group'}</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='group-name'>Group name</Label>
              <Input
                id='group-name'
                placeholder='e.g. Parents'
                value={form.name}
                onChange={(event) =>
                  setForm((current) => ({ ...current, name: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='group-desc'>Description</Label>
              <Textarea
                id='group-desc'
                placeholder='Who belongs in this group?'
                value={form.description}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    description: event.target.value,
                  }))
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              variant='outline'
              onClick={() => {
                setCreating(false)
                setEditing(null)
                setForm(emptyForm)
              }}
            >
              Cancel
            </Button>
            <Button
              disabled={saveMutation.isPending || form.name.trim() === ''}
              onClick={() => saveMutation.mutate()}
            >
              {saveMutation.isPending ? 'Saving…' : 'Save Group'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleting(null)
          }
        }}
        title='Delete this group?'
        desc={`${deleting?.name ?? 'This group'} will be removed. Contacts stay in your list; they are just ungrouped.`}
        confirmText={deleteMutation.isPending ? 'Deleting…' : 'Delete Group'}
        destructive
        isLoading={deleteMutation.isPending}
        handleConfirm={() => deleteMutation.mutate()}
      />
    </>
  )
}
