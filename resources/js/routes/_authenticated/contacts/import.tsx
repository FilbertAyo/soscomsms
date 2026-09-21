import { createFileRoute } from '@tanstack/react-router'
import { ImportContacts } from '@/features/contacts/import'

export const Route = createFileRoute('/_authenticated/contacts/import')({
  component: ImportContacts,
})
