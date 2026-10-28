import { createFileRoute } from '@tanstack/react-router'
import { AdminCustomerDetail } from '@/features/admin/customer-detail'

export const Route = createFileRoute(
  '/_authenticated/admin/customers/$customerId'
)({
  component: AdminCustomerDetailRoute,
})

function AdminCustomerDetailRoute() {
  const { customerId } = Route.useParams()
  return <AdminCustomerDetail customerId={customerId} />
}
