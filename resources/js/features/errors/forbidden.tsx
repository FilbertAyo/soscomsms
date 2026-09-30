import { FriendlyError } from './friendly-error'

export function ForbiddenError() {
  return (
    <FriendlyError
      title="You can't open this"
      description='Your account does not have access to this page. Go back, or return to your dashboard.'
      homeTo='/dashboard'
      homeLabel='Back to dashboard'
    />
  )
}
