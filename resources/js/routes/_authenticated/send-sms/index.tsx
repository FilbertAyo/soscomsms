import { createFileRoute } from '@tanstack/react-router'
import { SendSms } from '@/features/send-sms'

export const Route = createFileRoute('/_authenticated/send-sms/')({
  component: SendSms,
})
