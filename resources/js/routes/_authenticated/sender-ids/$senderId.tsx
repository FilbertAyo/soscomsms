import { createFileRoute } from '@tanstack/react-router'
import { SenderIdRequestDetail } from '@/features/sender-ids/detail'

export const Route = createFileRoute('/_authenticated/sender-ids/$senderId')({
  component: SenderIdDetailRoute,
})

function SenderIdDetailRoute() {
  const { senderId } = Route.useParams()
  return <SenderIdRequestDetail senderId={senderId} />
}
