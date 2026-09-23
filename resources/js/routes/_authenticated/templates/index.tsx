import { createFileRoute } from '@tanstack/react-router'
import { MessageTemplatesPage } from '@/features/message-templates'

export const Route = createFileRoute('/_authenticated/templates/')({
  component: MessageTemplatesPage,
})
