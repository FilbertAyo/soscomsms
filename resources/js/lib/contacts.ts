import { api } from '@/lib/api'
import {
  type Contact,
  type ContactGroup,
  type ContactImportResult,
  type ContactStatus,
} from '@/types/contacts'
import { emptyPageMeta, type PageMeta } from '@/types/pagination'

export const contactGroupsQueryKey = ['contact-groups'] as const

export function contactsQueryKey(filters: {
  q?: string
  groupId?: string
  status?: string
  page?: number
}) {
  return ['contacts', filters] as const
}

export async function fetchContactGroups(): Promise<ContactGroup[]> {
  const { data } = await api.get<{ groups: ContactGroup[] }>('/api/contact-groups')
  return data.groups
}

export async function createContactGroup(payload: {
  name: string
  description?: string
}): Promise<ContactGroup> {
  const { data } = await api.post<{ group: ContactGroup }>(
    '/api/contact-groups',
    payload
  )
  return data.group
}

export async function updateContactGroup(
  id: number,
  payload: { name: string; description?: string | null }
): Promise<ContactGroup> {
  const { data } = await api.patch<{ group: ContactGroup }>(
    `/api/contact-groups/${id}`,
    payload
  )
  return data.group
}

export async function deleteContactGroup(id: number): Promise<void> {
  await api.delete(`/api/contact-groups/${id}`)
}

export async function fetchContacts(filters: {
  q?: string
  groupId?: string
  status?: string
  page?: number
  perPage?: number
}): Promise<{ contacts: Contact[]; meta: PageMeta }> {
  const { data } = await api.get<{ contacts: Contact[]; meta?: PageMeta }>(
    '/api/contacts',
    {
      params: {
        q: filters.q || undefined,
        group_id: filters.groupId || undefined,
        status: filters.status || undefined,
        page: filters.page || undefined,
        per_page: filters.perPage || undefined,
      },
    }
  )
  return {
    contacts: data.contacts,
    meta: data.meta ?? emptyPageMeta,
  }
}

export async function createContact(payload: {
  name: string
  phone: string
  contact_group_id?: number | null
}): Promise<Contact> {
  const { data } = await api.post<{ contact: Contact }>('/api/contacts', payload)
  return data.contact
}

export async function updateContact(
  id: number,
  payload: {
    name?: string
    phone?: string
    contact_group_id?: number | null
    status?: ContactStatus
  }
): Promise<Contact> {
  const { data } = await api.patch<{ contact: Contact }>(
    `/api/contacts/${id}`,
    payload
  )
  return data.contact
}

export async function importContacts(
  file: File,
  columns: { name: string; phone: string; group: string }
): Promise<ContactImportResult> {
  const body = new FormData()
  body.append('file', file)
  body.append('name_column', columns.name)
  body.append('phone_column', columns.phone)
  body.append('group_column', columns.group)

  const { data } = await api.post<ContactImportResult>(
    '/api/contacts/import',
    body
  )
  return data
}
