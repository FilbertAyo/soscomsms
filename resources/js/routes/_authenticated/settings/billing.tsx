import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/_authenticated/settings/billing')({
  beforeLoad: () => {
    throw redirect({ to: '/wallet' })
  },
  component: () => null,
})
