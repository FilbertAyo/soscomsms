import { createFileRoute } from '@tanstack/react-router'
import { DeveloperPage } from '@/features/developer'

export const Route = createFileRoute('/_authenticated/developer/')({
  component: DeveloperPage,
})
