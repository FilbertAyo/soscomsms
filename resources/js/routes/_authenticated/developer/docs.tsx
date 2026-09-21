import { createFileRoute } from '@tanstack/react-router'
import { ApiDocsPage } from '@/features/developer/docs'

export const Route = createFileRoute('/_authenticated/developer/docs')({
  component: ApiDocsPage,
})
