import { createFileRoute } from '@tanstack/react-router'
import { ContactGroupsPage } from '@/features/contacts/groups'

export const Route = createFileRoute('/_authenticated/contacts/groups')({
  component: ContactGroupsPage,
})
