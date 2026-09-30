import { FriendlyError } from './friendly-error'

export function MaintenanceError() {
  return (
    <FriendlyError
      title='Soscom SMS is briefly unavailable'
      description='We are updating the service. Please try again in a few minutes.'
      homeTo='/'
      homeLabel='Back to Home'
    />
  )
}
