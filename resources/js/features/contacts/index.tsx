import { useDeferredValue, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { toast } from 'sonner'
import { Users } from 'lucide-react'
import { applyValidationErrors } from '@/lib/auth'
import {
  contactGroupsQueryKey,
  contactsQueryKey,
  createContact,
  fetchContactGroups,
  fetchContacts,
  updateContact,
} from '@/lib/contacts'
import { formatDate } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { ListPagination } from '@/components/soscom/list-pagination'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { type Contact, type ContactStatus } from '@/types/contacts'

type ContactForm = {
  name: string
  phone: string
  groupId: string
}

const emptyForm: ContactForm = {
  name: '',
  phone: '',
  groupId: 'none',
}

function statusActionLabel(status: ContactStatus): string {
  switch (status) {
    case 'active':
      return 'Deactivate'
    case 'inactive':
      return 'Reactivate'
    default: {
      const exhaustive: never = status
      return exhaustive
    }
  }
}

function nextStatus(status: ContactStatus): ContactStatus {
  switch (status) {
    case 'active':
      return 'inactive'
    case 'inactive':
      return 'active'
    default: {
      const exhaustive: never = status
      return exhaustive
    }
  }
}

export function ContactsPage() {
  const queryClient = useQueryClient()
  const [query, setQuery] = useState('')
  const deferredQuery = useDeferredValue(query)
  const [groupFilter, setGroupFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [form, setForm] = useState<ContactForm>(emptyForm)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Contact | null>(null)
  const [statusTarget, setStatusTarget] = useState<Contact | null>(null)

  const filters = {
    q: deferredQuery.trim(),
    groupId: groupFilter === 'all' ? '' : groupFilter,
    status: statusFilter === 'all' ? '' : statusFilter,
    page,
  }

  const groupsQuery = useQuery({
    queryKey: contactGroupsQueryKey,
    queryFn: fetchContactGroups,
  })
  const contactsQuery = useQuery({
    queryKey: contactsQueryKey(filters),
    queryFn: () => fetchContacts(filters),
  })
  const groups = groupsQuery.data ?? []
  const contacts = contactsQuery.data?.contacts ?? []
  const meta = contactsQuery.data?.meta

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        phone: form.phone.trim(),
        contact_group_id: form.groupId === 'none' ? null : Number(form.groupId),
      }

      if (editing) {
        return updateContact(editing.id, payload)
      }

      return createContact(payload)
    },
    onSuccess: async () => {
      toast.success(editing ? 'Contact updated.' : 'Contact added.')
      setCreating(false)
      setEditing(null)
      setForm(emptyForm)
      void queryClient.invalidateQueries({ queryKey: ['contacts'] })
      void queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const statusMutation = useMutation({
    mutationFn: () => {
      if (!statusTarget) {
        throw new Error('No contact selected.')
      }

      return updateContact(statusTarget.id, {
        status: nextStatus(statusTarget.status),
      })
    },
    onSuccess: async (contact) => {
      toast.success(
        contact.status === 'inactive'
          ? 'Contact deactivated.'
          : 'Contact reactivated.'
      )
      setStatusTarget(null)
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

  function openEdit(contact: Contact) {
    setCreating(false)
    setEditing(contact)
    setForm({
      name: contact.name,
      phone: contact.phone,
      groupId: contact.groupId ? String(contact.groupId) : 'none',
    })
  }

  const dialogOpen = creating || editing !== null
  const hasFilters =
    query.trim() !== '' || groupFilter !== 'all' || statusFilter !== 'all'

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='All Contacts'
          description='Keep the people you message in one place. Numbers are stored as Tanzanian +255 mobiles.'
          actions={
            <>
              <Button variant='outline' asChild>
                <Link to='/contacts/import'>Import Contacts</Link>
              </Button>
              <Button variant='outline' asChild>
                <Link to='/contacts/groups'>Create Group</Link>
              </Button>
              <Button onClick={openCreate}>Add Contact</Button>
            </>
          }
        />
        <div className='mb-4 flex flex-col gap-3 sm:flex-row'>
          <Input
            className='max-w-sm'
            placeholder='Search by name, phone, or group'
            value={query}
            onChange={(event) => {
              setQuery(event.target.value)
              setPage(1)
            }}
          />
          <Select
            value={groupFilter}
            onValueChange={(value) => {
              setGroupFilter(value)
              setPage(1)
            }}
          >
            <SelectTrigger className='sm:w-48'>
              <SelectValue placeholder='All groups' />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All groups</SelectItem>
              {groups.map((group) => (
                <SelectItem key={group.id} value={String(group.id)}>
                  {group.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={statusFilter}
            onValueChange={(value) => {
              setStatusFilter(value)
              setPage(1)
            }}
          >
            <SelectTrigger className='sm:w-44'>
              <SelectValue placeholder='All statuses' />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='all'>All statuses</SelectItem>
              <SelectItem value='active'>Active</SelectItem>
              <SelectItem value='inactive'>Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
        {contactsQuery.isError ? (
          <p className='text-sm text-destructive'>
            Could not load contacts. Refresh and try again.
          </p>
        ) : contacts.length === 0 && !contactsQuery.isPending ? (
          <EmptyState
            icon={Users}
            title={hasFilters ? 'No contacts found' : 'No contacts yet'}
            description={
              hasFilters
                ? 'Try another search, or clear the filters.'
                : 'Add a Tanzanian number, or import a CSV.'
            }
            actionLabel={hasFilters ? undefined : 'Add Contact'}
            onAction={hasFilters ? undefined : openCreate}
          />
        ) : (
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Phone Number</TableHead>
                  <TableHead>Group</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead className='text-end'>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {contacts.map((contact) => (
                  <TableRow key={contact.id}>
                    <TableCell className='font-medium'>{contact.name}</TableCell>
                    <TableCell>{contact.phone}</TableCell>
                    <TableCell>{contact.groupName ?? 'Ungrouped'}</TableCell>
                    <TableCell>
                      <StatusBadge status={contact.status} />
                    </TableCell>
                    <TableCell>
                      {contact.createdAt ? formatDate(contact.createdAt) : '—'}
                    </TableCell>
                    <TableCell className='text-end'>
                      <Button
                        variant='ghost'
                        size='sm'
                        onClick={() => openEdit(contact)}
                      >
                        Edit
                      </Button>
                      <Button
                        variant='ghost'
                        size='sm'
                        onClick={() => setStatusTarget(contact)}
                      >
                        {statusActionLabel(contact.status)}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableWrap>
        )}
        {meta ? <ListPagination meta={meta} onPageChange={setPage} /> : null}
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
            <DialogTitle>{editing ? 'Edit Contact' : 'Add Contact'}</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='contact-name'>Name</Label>
              <Input
                id='contact-name'
                placeholder='e.g. Juma Hassan'
                value={form.name}
                onChange={(event) =>
                  setForm((current) => ({ ...current, name: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='contact-phone'>Phone Number</Label>
              <Input
                id='contact-phone'
                placeholder='0712 445 221'
                value={form.phone}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    phone: event.target.value,
                  }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>Group</Label>
              <Select
                value={form.groupId}
                onValueChange={(value) =>
                  setForm((current) => ({ ...current, groupId: value }))
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='none'>Ungrouped</SelectItem>
                  {groups.map((group) => (
                    <SelectItem key={group.id} value={String(group.id)}>
                      {group.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
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
              disabled={
                saveMutation.isPending ||
                form.name.trim() === '' ||
                form.phone.trim() === ''
              }
              onClick={() => saveMutation.mutate()}
            >
              {saveMutation.isPending ? 'Saving…' : 'Save Contact'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={statusTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setStatusTarget(null)
          }
        }}
        title={
          statusTarget?.status === 'active'
            ? 'Deactivate this contact?'
            : 'Reactivate this contact?'
        }
        desc={
          statusTarget?.status === 'active'
            ? `${statusTarget.name} will stay in your list, but will not be messaged until you reactivate them.`
            : `${statusTarget?.name ?? 'This contact'} will be available for messaging again.`
        }
        confirmText={
          statusMutation.isPending
            ? 'Saving…'
            : statusActionLabel(statusTarget?.status ?? 'active')
        }
        destructive={statusTarget?.status === 'active'}
        isLoading={statusMutation.isPending}
        handleConfirm={() => statusMutation.mutate()}
      />
    </>
  )
}
