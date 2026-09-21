import { ContentSection } from '../components/content-section'
import { AccountForm } from './account-form'

export function SettingsAccount() {
  return (
    <ContentSection
      title='Account'
      desc='Update the name and email you use to sign in.'
    >
      <AccountForm />
    </ContentSection>
  )
}
