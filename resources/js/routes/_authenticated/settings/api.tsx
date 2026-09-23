import { createFileRoute } from '@tanstack/react-router'
import { SettingsApi } from '@/features/settings/api'
import { assertDeveloperApiVisible } from '@/lib/features'

export const Route = createFileRoute('/_authenticated/settings/api')({
  beforeLoad: () => {
    assertDeveloperApiVisible()
  },
  component: SettingsApi,
})
