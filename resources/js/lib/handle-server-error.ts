import { toast } from 'sonner'
import { normalizeError, safeUserText } from '@/lib/api-error'
import { logClientError } from '@/lib/log-client-error'

export { safeUserText } from '@/lib/api-error'

export function userFacingMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.'
): string {
  return safeUserText(normalizeError(error).message, fallback)
}

export function handleServerError(error: unknown) {
  logClientError(error)

  if (
    error &&
    typeof error === 'object' &&
    'status' in error &&
    Number(error.status) === 204
  ) {
    toast.error('Nothing to update.')
    return
  }

  toast.error(userFacingMessage(error))
}
