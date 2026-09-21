import { Link } from '@tanstack/react-router'
import { Button } from '@/components/ui/button'
import { ContentSection } from '../components/content-section'

export function SettingsApi() {
  return (
    <ContentSection
      title='API'
      desc='Create and revoke API keys from the developer section.'
    >
      <div className='space-y-3'>
        <p className='text-sm text-muted-foreground'>
          API keys let your website or application send SMS through Soscom SMS.
        </p>
        <Button asChild>
          <Link to='/developer'>Manage API keys</Link>
        </Button>
      </div>
    </ContentSection>
  )
}
