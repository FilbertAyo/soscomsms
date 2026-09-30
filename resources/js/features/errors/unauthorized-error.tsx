import { FriendlyError } from './friendly-error'

export function UnauthorisedError() {
  return (
    <FriendlyError
      title='Please sign in'
      description='Your session ended, or this page needs you to sign in first.'
      homeTo='/'
      homeLabel='Back to Home'
    />
  )
}
