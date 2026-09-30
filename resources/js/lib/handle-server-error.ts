import { AxiosError } from 'axios'
import { toast } from 'sonner'

const TECHNICAL =
  /sqlstate|stack trace|exception|vendor[\\/]|\\\\|undefined method|syntax error|pdo|illuminate\\|\.php on line/i

function looksTechnical(message: string): boolean {
  return (
    message.length > 280 ||
    message.includes('\n') ||
    TECHNICAL.test(message)
  )
}

export function safeUserText(
  message: string | null | undefined,
  fallback: string
): string {
  if (typeof message !== 'string') {
    return fallback
  }

  const trimmed = message.trim()
  if (trimmed.length === 0 || looksTechnical(trimmed)) {
    return fallback
  }

  return trimmed
}

export function userFacingMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.'
): string {
  if (error instanceof Error && !(error instanceof AxiosError)) {
    return safeUserText(error.message, fallback)
  }

  if (!(error instanceof AxiosError)) {
    return fallback
  }

  if (!error.response) {
    return 'We could not reach Soscom. Check your connection and try again.'
  }

  const status = error.response.status

  if (status === 401) {
    return 'Your session ended. Sign in again.'
  }

  if (status === 403) {
    return 'You do not have permission to do that.'
  }

  if (status === 404) {
    return "We couldn't find that. Go back and try again."
  }

  if (status === 419) {
    return 'Your session expired. Refresh the page and try again.'
  }

  if (status === 429) {
    return 'Too many attempts. Wait a moment and try again.'
  }

  if (status >= 500) {
    console.error(error)
    return 'Something went wrong on our side. Please try again.'
  }

  const errors = error.response.data?.errors
  if (errors && typeof errors === 'object') {
    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && typeof messages[0] === 'string') {
        return safeUserText(messages[0], fallback)
      }
    }
  }

  if (typeof error.response.data?.message === 'string') {
    return safeUserText(error.response.data.message, fallback)
  }

  return fallback
}

export function handleServerError(error: unknown) {
  console.error(error)

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
