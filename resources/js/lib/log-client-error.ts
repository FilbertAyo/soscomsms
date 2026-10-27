import { normalizeError, requestMethod, requestUrl } from '@/lib/api-error'

export function logClientError(
  error: unknown,
  extra: Record<string, unknown> = {}
): void {
  const normalized = normalizeError(error)

  console.error('[soscom]', {
    code: normalized.code,
    status: normalized.status,
    requestId: normalized.requestId,
    method: requestMethod(error),
    url: requestUrl(error),
    retryable: normalized.retryable,
    ...extra,
    originalError: normalized.originalError,
  })
}
