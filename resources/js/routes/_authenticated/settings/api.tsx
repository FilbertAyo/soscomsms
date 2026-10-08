import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/_authenticated/settings/api')({
  beforeLoad: () => {
    throw redirect({ to: '/developer' })
  },
  component: () => null,
})
