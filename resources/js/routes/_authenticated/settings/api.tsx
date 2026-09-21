import { createFileRoute } from '@tanstack/react-router'
import { SettingsApi } from '@/features/settings/api'

export const Route = createFileRoute('/_authenticated/settings/api')({
  component: SettingsApi,
})
