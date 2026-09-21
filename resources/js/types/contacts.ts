export const CONTACT_STATUSES = ['active', 'inactive'] as const

export type ContactStatus = (typeof CONTACT_STATUSES)[number]

export type ContactGroup = {
  id: number
  name: string
  description: string | null
  contactsCount: number
}

export type Contact = {
  id: number
  name: string
  phone: string
  status: ContactStatus
  groupId: number | null
  groupName: string | null
  createdAt: string | null
}

export type ContactImportResult = {
  created: number
  skipped: number
  invalid: number
  status?: 'queued' | 'completed'
  message?: string
  errors: Array<{ row: number; reason: string }>
}
