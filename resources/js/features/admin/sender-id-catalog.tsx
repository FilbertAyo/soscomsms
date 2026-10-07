import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import { fetchAdminCustomers } from '@/lib/credits'
import {
  adminSenderIdCatalogQueryKey,
  createAdminSenderId,
  deleteAdminSenderId,
  fetchAdminSenderIdCatalog,
  grantAdminSenderId,
  revokeAdminSenderId,
  updateAdminSenderId,
  usableSenderIdsQueryKey,
} from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { StatusBadge } from '@/components/soscom/status-badge'
import { type AdminSenderIdCatalogRecord } from '@/types/sender-ids'
import { type AdminCustomer } from '@/types/credits'
import { AdminPage } from './page'

const MIN_CUSTOMER_SEARCH = 2

export function AdminSenderIdCatalog() {
  const queryClient = useQueryClient()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [addOpen, setAddOpen] = useState(false)
  const [name, setName] = useState('')
  const [generic, setGeneric] = useState(true)
  const [availableToAll, setAvailableToAll] = useState(false)
  const [listFilter, setListFilter] = useState('')
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [deleteOpen, setDeleteOpen] = useState(false)

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => window.clearTimeout(timer)
  }, [search])

  const catalogQuery = useQuery({
    queryKey: adminSenderIdCatalogQueryKey,
    queryFn: fetchAdminSenderIdCatalog,
  })
  const senderIds = catalogQuery.data ?? []
  const selected =
    senderIds.find((item) => item.id === selectedId) ?? senderIds[0] ?? null
  const filterTerm = listFilter.trim().toLowerCase()
  const visibleSenderIds = filterTerm
    ? senderIds.filter((item) => item.name.toLowerCase().includes(filterTerm))
    : senderIds

  const customerSearchReady = debouncedSearch.length >= MIN_CUSTOMER_SEARCH
  const customersQuery = useQuery({
    queryKey: ['admin', 'customers', 'sender-id-grants', debouncedSearch],
    queryFn: () => fetchAdminCustomers(debouncedSearch, 1),
    enabled:
      customerSearchReady && selected !== null && !selected.availableToAll,
  })
  const searchResults = customerSearchReady
    ? (customersQuery.data?.customers ?? [])
    : []

  function refresh() {
    void queryClient.invalidateQueries({ queryKey: adminSenderIdCatalogQueryKey })
    void queryClient.invalidateQueries({ queryKey: usableSenderIdsQueryKey })
  }

  const createMutation = useMutation({
    mutationFn: () =>
      createAdminSenderId({
        name: name.trim(),
        generic,
        available_to_all: availableToAll,
      }),
    onSuccess: (created) => {
      toast.success(`${created.name} added.`)
      setAddOpen(false)
      setName('')
      setGeneric(true)
      setAvailableToAll(false)
      setSelectedId(created.id)
      refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const updateMutation = useMutation({
    mutationFn: (payload: {
      generic?: boolean
      available_to_all?: boolean
      status?: 'active' | 'inactive'
    }) => {
      if (!selected) {
        throw new Error('No Sender ID selected.')
      }
      return updateAdminSenderId(selected.id, payload)
    },
    onSuccess: () => {
      toast.success('Sender ID updated.')
      refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const grantMutation = useMutation({
    mutationFn: (customerId: number) => {
      if (!selected) {
        throw new Error('No Sender ID selected.')
      }
      return grantAdminSenderId(selected.id, customerId)
    },
    onSuccess: () => {
      toast.success('Customer can now use this Sender ID.')
      setSearch('')
      refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const revokeMutation = useMutation({
    mutationFn: (customerId: number) => {
      if (!selected) {
        throw new Error('No Sender ID selected.')
      }
      return revokeAdminSenderId(selected.id, customerId)
    },
    onSuccess: () => {
      toast.success('Access removed.')
      refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const deleteMutation = useMutation({
    mutationFn: () => {
      if (!selected) {
        throw new Error('No Sender ID selected.')
      }
      return deleteAdminSenderId(selected.id)
    },
    onSuccess: () => {
      toast.success('Sender ID removed.')
      setDeleteOpen(false)
      setSelectedId(null)
      refresh()
    },
    onError: (error) => toast.error(applyValidationErrors(error, () => undefined)),
  })

  const grantedIds = useMemo(
    () => new Set((selected?.grants ?? []).map((item) => item.id)),
    [selected]
  )
  const grantCandidates = searchResults.filter(
    (customer) => !grantedIds.has(customer.id)
  )

  return (
    <AdminPage
      title='Sender IDs'
      description='Add names from the SMS provider, choose who can use them, and grant access to specific customers.'
      actions={
        <Button onClick={() => setAddOpen(true)}>Add Sender ID</Button>
      }
    >
      {catalogQuery.isError ? (
        <p className='text-sm text-muted-foreground'>
          We couldn't load Sender IDs. Refresh the page and try again.
        </p>
      ) : (
        <div className='grid items-start gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]'>
          <div className='rounded-lg border bg-card'>
            <div className='flex items-center justify-between gap-2 border-b px-3 py-2'>
              <span className='text-xs font-medium'>
                {senderIds.length === 1
                  ? '1 Sender ID'
                  : `${senderIds.length} Sender IDs`}
              </span>
            </div>
            <div className='border-b p-2'>
              <div className='relative'>
                <Search className='pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground' />
                <Input
                  type='search'
                  value={listFilter}
                  onChange={(event) => setListFilter(event.target.value)}
                  placeholder='Filter Sender IDs'
                  className='h-8 ps-8 text-xs'
                  aria-label='Filter Sender IDs'
                />
              </div>
            </div>
            {senderIds.length === 0 ? (
              <p className='p-3 text-xs text-muted-foreground'>
                No Sender IDs yet. Add the names you received from the provider.
              </p>
            ) : visibleSenderIds.length === 0 ? (
              <p className='p-3 text-xs text-muted-foreground'>
                No Sender IDs match “{listFilter.trim()}”.
              </p>
            ) : (
              <div className='max-h-[28rem] divide-y overflow-y-auto'>
                {visibleSenderIds.map((item) => (
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
                        {typeLabel(item)} · {visibilityLabel(item)}
                      </span>
                    </span>
                    {item.status !== 'active' ? (
                      <StatusBadge
                        status={item.status}
                        className='px-1.5 py-0 text-[10px] leading-4'
                      />
                    ) : null}
                  </button>
                ))}
              </div>
            )}
          </div>
          {selected ? (
            <CatalogDetail
              senderId={selected}
              pending={
                updateMutation.isPending ||
                grantMutation.isPending ||
                revokeMutation.isPending
              }
              search={search}
              onSearch={setSearch}
              candidates={grantCandidates}
              searchReady={customerSearchReady}
              customersLoading={customerSearchReady && customersQuery.isFetching}
              onToggleAll={(value) =>
                updateMutation.mutate({ available_to_all: value })
              }
              onToggleGeneric={(value) =>
                updateMutation.mutate({ generic: value })
              }
              onToggleActive={(active) =>
                updateMutation.mutate({
                  status: active ? 'active' : 'inactive',
                })
              }
              onGrant={(customerId) => grantMutation.mutate(customerId)}
              onRevoke={(customerId) => revokeMutation.mutate(customerId)}
              onDelete={() => setDeleteOpen(true)}
            />
          ) : null}
        </div>
      )}

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Sender ID</DialogTitle>
          </DialogHeader>
          <form
            className='space-y-4'
            onSubmit={(event) => {
              event.preventDefault()
              if (name.trim().length < 3) {
                toast.error('Enter a Sender ID of at least 3 characters.')
                return
              }
              createMutation.mutate()
            }}
          >
            <div className='space-y-2'>
              <Label htmlFor='catalog-name'>Sender ID</Label>
              <Input
                id='catalog-name'
                value={name}
                maxLength={13}
                onChange={(event) =>
                  setName(event.target.value.replaceAll(/\s+/g, ' ').trimStart())
                }
              />
              <p className='text-xs text-muted-foreground'>
                3 to 13 letters, numbers, or single spaces (for example SEND OFF).
              </p>
            </div>
            <ToggleRow
              id='catalog-generic'
              label='Generic'
              description='A shared Soscom name, not one customer’s brand.'
              checked={generic}
              onCheckedChange={setGeneric}
            />
            <ToggleRow
              id='catalog-all'
              label='Show to all customers'
              description='If off, only customers you grant can use it.'
              checked={availableToAll}
              onCheckedChange={setAvailableToAll}
            />
            <DialogFooter>
              <Button
                type='button'
                variant='outline'
                onClick={() => setAddOpen(false)}
              >
                Cancel
              </Button>
              <Button type='submit' disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Adding…' : 'Add Sender ID'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title='Remove this Sender ID?'
        desc='Customers will no longer see it. Names already used on campaigns cannot be removed.'
        confirmText={deleteMutation.isPending ? 'Removing…' : 'Remove'}
        isLoading={deleteMutation.isPending}
        destructive
        handleConfirm={() => deleteMutation.mutate()}
      />
    </AdminPage>
  )
}

function CatalogDetail({
  senderId,
  pending,
  search,
  onSearch,
  candidates,
  searchReady,
  customersLoading,
  onToggleAll,
  onToggleGeneric,
  onToggleActive,
  onGrant,
  onRevoke,
  onDelete,
}: {
  senderId: AdminSenderIdCatalogRecord
  pending: boolean
  search: string
  onSearch: (value: string) => void
  candidates: AdminCustomer[]
  searchReady: boolean
  customersLoading: boolean
  onToggleAll: (value: boolean) => void
  onToggleGeneric: (value: boolean) => void
  onToggleActive: (value: boolean) => void
  onGrant: (customerId: number) => void
  onRevoke: (customerId: number) => void
  onDelete: () => void
}) {
  const requestOwned = senderId.source === 'request'

  return (
    <div className='space-y-4 rounded-lg border bg-card p-4'>
      <div className='flex items-start justify-between gap-3'>
        <div className='min-w-0'>
          <h2 className='truncate text-base font-semibold'>{senderId.name}</h2>
          <p className='text-xs text-muted-foreground'>
            {typeLabel(senderId)} · {visibilityLabel(senderId)}
          </p>
        </div>
        <StatusBadge status={senderId.status} />
      </div>

      <ToggleRow
        id={`active-${senderId.id}`}
        label='Active'
        description='Inactive names cannot be used to send SMS.'
        checked={senderId.status === 'active'}
        disabled={pending}
        onCheckedChange={onToggleActive}
      />
      <ToggleRow
        id={`generic-${senderId.id}`}
        label='Generic'
        description={
          requestOwned
            ? 'This name came from a customer request.'
            : 'A shared Soscom name, not one customer’s brand.'
        }
        checked={senderId.source === 'generic'}
        disabled={pending || requestOwned}
        onCheckedChange={onToggleGeneric}
      />
      <ToggleRow
        id={`all-${senderId.id}`}
        label='Show to all customers'
        description='If off, only granted customers see this name when sending SMS.'
        checked={senderId.availableToAll}
        disabled={pending}
        onCheckedChange={onToggleAll}
      />

      {senderId.availableToAll ? (
        <p className='text-xs text-muted-foreground'>
          Every customer can use {senderId.name}. Turn off “Show to all
          customers” to limit it to people you grant.
        </p>
      ) : (
        <div className='space-y-3 border-t pt-4'>
          <div className='space-y-1.5'>
            <Label htmlFor='grant-search'>Grant to a customer</Label>
            <div className='relative'>
              <Search className='pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground' />
              <Input
                id='grant-search'
                type='search'
                className='h-8 ps-8 text-xs'
                placeholder='Search name, email, phone, or business'
                value={search}
                onChange={(event) => onSearch(event.target.value)}
              />
            </div>
            {!searchReady ? (
              <p className='text-[11px] text-muted-foreground'>
                Type at least {MIN_CUSTOMER_SEARCH} characters to find a
                customer.
              </p>
            ) : customersLoading ? (
              <p className='text-[11px] text-muted-foreground'>Searching…</p>
            ) : candidates.length === 0 ? (
              <p className='text-[11px] text-muted-foreground'>
                No customers found without access.
              </p>
            ) : null}
          </div>
          {grantCandidatesList(candidates, onGrant, pending)}
          <div>
            <p className='mb-1.5 text-xs font-medium'>
              Who can use it ({senderId.grants.length})
            </p>
            {senderId.grants.length === 0 ? (
              <p className='text-xs text-muted-foreground'>
                No customers yet. Search and grant access.
              </p>
            ) : (
              <ul className='max-h-64 divide-y overflow-y-auto rounded-lg border'>
                {senderId.grants.map((grant) => (
                  <CustomerRow
                    key={grant.id}
                    title={grant.businessName ?? grant.name ?? 'Customer'}
                    email={grant.email}
                  >
                    <Button
                      type='button'
                      size='sm'
                      variant='ghost'
                      className='h-7 px-2 text-xs'
                      disabled={pending}
                      onClick={() => onRevoke(grant.id)}
                    >
                      Remove
                    </Button>
                  </CustomerRow>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}

      <Button
        type='button'
        variant='outline'
        size='sm'
        disabled={senderId.campaignCount > 0}
        onClick={onDelete}
      >
        Remove Sender ID
      </Button>
      {senderId.campaignCount > 0 ? (
        <p className='text-xs text-muted-foreground'>
          This name has been used on campaigns, so it can only be deactivated.
        </p>
      ) : null}
    </div>
  )
}

function grantCandidatesList(
  candidates: AdminCustomer[],
  onGrant: (customerId: number) => void,
  pending: boolean
) {
  if (candidates.length === 0) {
    return null
  }

  return (
    <ul className='max-h-56 divide-y overflow-y-auto rounded-lg border'>
      {candidates.map((customer) => (
        <CustomerRow
          key={customer.id}
          title={customer.businessName ?? customer.name ?? 'Customer'}
          email={customer.email}
        >
          <Button
            type='button'
            size='sm'
            className='h-7 px-2 text-xs'
            disabled={pending}
            onClick={() => onGrant(customer.id)}
          >
            Grant
          </Button>
        </CustomerRow>
      ))}
    </ul>
  )
}

function CustomerRow({
  title,
  email,
  children,
}: {
  title: string
  email: string | null | undefined
  children: React.ReactNode
}) {
  return (
    <li className='flex items-center justify-between gap-3 px-3 py-1.5'>
      <div className='min-w-0'>
        <p className='truncate text-xs font-medium'>{title}</p>
        <p className='truncate text-[11px] text-muted-foreground'>{email}</p>
      </div>
      {children}
    </li>
  )
}

function ToggleRow({
  id,
  label,
  description,
  checked,
  disabled,
  onCheckedChange,
}: {
  id: string
  label: string
  description: string
  checked: boolean
  disabled?: boolean
  onCheckedChange: (value: boolean) => void
}) {
  return (
    <div className='flex items-start justify-between gap-4'>
      <div>
        <Label htmlFor={id}>{label}</Label>
        <p className='text-xs text-muted-foreground'>{description}</p>
      </div>
      <Switch
        id={id}
        checked={checked}
        disabled={disabled}
        onCheckedChange={onCheckedChange}
      />
    </div>
  )
}

function typeLabel(item: AdminSenderIdCatalogRecord): string {
  if (item.source === 'generic') {
    return 'Generic'
  }
  if (item.source === 'request') {
    return 'Customer request'
  }
  return 'Owned'
}

function visibilityLabel(item: AdminSenderIdCatalogRecord): string {
  if (item.availableToAll) {
    return 'All customers'
  }
  const count = item.grants.length + (item.customerId ? 1 : 0)
  return count === 1 ? '1 customer' : `${count} customers`
}
