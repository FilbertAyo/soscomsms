import { Link, useLocation } from '@tanstack/react-router'
import { useAuthStore } from '@/stores/auth-store'
import { needsProfileCompletion } from '@/types/auth'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

export function ProfileGateBanner() {
  const user = useAuthStore((state) => state.user)
  const pathname = useLocation({ select: (location) => location.pathname })

  if (!user || !needsProfileCompletion(user) || pathname.startsWith('/settings')) {
    return null
  }

  return (
    <div className='px-4 pt-4'>
      <Alert>
        <AlertTitle>Complete your profile</AlertTitle>
        <AlertDescription>
          Add your business name and Tanzanian phone number to unlock Send SMS.{' '}
          <Link to='/settings/business' className='font-medium underline underline-offset-4'>
            Complete now
          </Link>
        </AlertDescription>
      </Alert>
    </div>
  )
}
