import { createFileRoute } from '@tanstack/react-router'
import { DeveloperPage } from '@/features/developer'
import { assertDeveloperApiVisible } from '@/lib/features'

export const Route = createFileRoute('/_authenticated/developer/')({
  beforeLoad: () => {
    assertDeveloperApiVisible()
  },
  component: DeveloperPage,
})
