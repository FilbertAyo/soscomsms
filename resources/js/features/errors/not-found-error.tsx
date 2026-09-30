import { FriendlyError } from './friendly-error'

export function NotFoundError() {
  return (
    <FriendlyError
      title="We can't find that page"
      description='The link may be wrong, or the page may have moved. Go back, or return to your dashboard.'
      homeTo='/dashboard'
      homeLabel='Back to dashboard'
    />
  )
}
