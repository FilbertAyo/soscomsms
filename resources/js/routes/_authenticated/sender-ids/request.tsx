import { createFileRoute } from '@tanstack/react-router'
import { RequestSenderId } from '@/features/sender-ids/request'

export const Route = createFileRoute('/_authenticated/sender-ids/request')({
  component: RequestSenderId,
})
