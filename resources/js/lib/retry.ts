import { isRetryableQueryError } from '@/lib/api-error'

const MAX_RETRIES = 2

export function queryRetry(failureCount: number, error: unknown): boolean {
  if (failureCount >= MAX_RETRIES) {
    return false
  }

  return isRetryableQueryError(error)
}

export function queryRetryDelay(failureCount: number): number {
  return Math.min(500 * 2 ** failureCount, 2000)
}
