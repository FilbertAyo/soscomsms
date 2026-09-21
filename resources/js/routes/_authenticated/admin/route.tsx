import { Outlet, createFileRoute, redirect } from '@tanstack/react-router'
import { isAdmin } from '@/types/auth'

export const Route = createFileRoute('/_authenticated/admin')({
  beforeLoad: ({ context }) => {
    if (!context.user || !isAdmin(context.user)) {
      throw redirect({ to: '/403' })
    }
  },
  component: AdminLayout,
})

function AdminLayout() {
  return <Outlet />
}
