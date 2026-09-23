import { createFileRoute } from '@tanstack/react-router'
import { ApiDocsPage } from '@/features/developer/docs'
import { assertDeveloperApiVisible } from '@/lib/features'

export const Route = createFileRoute('/_authenticated/developer/docs')({
  beforeLoad: () => {
    assertDeveloperApiVisible()
  },
  component: ApiDocsPage,
})
