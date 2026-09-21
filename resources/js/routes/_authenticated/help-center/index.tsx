import { createFileRoute } from '@tanstack/react-router'
import { HelpPage } from '@/features/help'

export const Route = createFileRoute('/_authenticated/help-center/')({
  component: HelpPage,
})
