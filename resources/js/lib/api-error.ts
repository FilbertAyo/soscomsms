import { AxiosError } from 'axios'

export type AppErrorCode =
  | 'VALIDATION_ERROR'
  | 'UNAUTHORIZED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'CONFLICT'
  | 'RATE_LIMITED'
  | 'CSRF_MISMATCH'
  | 'NETWORK_ERROR'
  | 'TIMEOUT'
  | 'SERVER_ERROR'
  | 'SERVICE_UNAVAILABLE'
  | 'UNKNOWN_ERROR'

export type AppError = {
  status: number
  code: AppErrorCode
  message: string
  retryable: boolean
  requestId: string | null
  fieldErrors: Record<string, string>
  originalError: unknown
}

const TECHNICAL =
  /sqlstate|stack trace|exception|vendor[\\/]|\\\\|undefined method|syntax error|pdo|illuminate\\|\.php on line|axioserror|failed with status code/i

const STATUS_CODES: Record<number, AppErrorCode> = {
  401: 'UNAUTHORIZED',
  403: 'FORBIDDEN',
  404: 'NOT_FOUND',
  409: 'CONFLICT',
  419: 'CSRF_MISMATCH',
  422: 'VALIDATION_ERROR',
  429: 'RATE_LIMITED',
  503: 'SERVICE_UNAVAILABLE',
}

const FALLBACKS: Record<AppErrorCode, string> = {
  VALIDATION_ERROR: 'Please check the highlighted fields and try again.',
  UNAUTHORIZED: 'Your session ended. Sign in again.',
  FORBIDDEN: 'You do not have permission to do that.',
  NOT_FOUND: "We couldn't find that.",
  CONFLICT: 'We could not complete that action right now. Please try again.',
  RATE_LIMITED: 'Too many attempts. Wait a moment and try again.',
  CSRF_MISMATCH: 'Your session expired. Refresh the page and try again.',
  NETWORK_ERROR: 'We could not reach Soscom. Check your connection and try again.',
  TIMEOUT: 'That took too long. Please try again.',
  SERVER_ERROR: 'Something went wrong. Please try again.',
  SERVICE_UNAVAILABLE: 'The service is temporarily unavailable. Please try again.',
  UNKNOWN_ERROR: 'Something went wrong. Please try again.',
}

export function looksTechnical(message: string): boolean {
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

function knownCode(value: unknown): AppErrorCode | null {
  if (typeof value !== 'string') {
    return null
  }

  const code = value.toUpperCase().replace(/-/g, '_')
  if (code in FALLBACKS) {
    return code as AppErrorCode
  }

  return null
}

function fieldErrorsFrom(data: unknown): Record<string, string> {
  if (!data || typeof data !== 'object' || !('errors' in data)) {
    return {}
  }

  const errors = (data as { errors?: unknown }).errors
  if (!errors || typeof errors !== 'object') {
    return {}
  }

  const result: Record<string, string> = {}
  for (const [field, messages] of Object.entries(errors)) {
    if (Array.isArray(messages) && typeof messages[0] === 'string') {
      const text = safeUserText(messages[0], '')
      if (text !== '') {
        result[field] = text
      }
    }
  }

  return result
}

function requestIdFrom(error: AxiosError): string | null {
  const header = error.response?.headers?.['x-request-id']
  if (typeof header === 'string' && header !== '') {
    return header
  }

  const body = error.response?.data
  if (body && typeof body === 'object' && 'request_id' in body) {
    const id = (body as { request_id?: unknown }).request_id
    if (typeof id === 'string' && id !== '') {
      return id
    }
  }

  return null
}

function backendMessage(data: unknown): string | null {
  if (!data || typeof data !== 'object') {
    return null
  }

  const payload = data as { message?: unknown }
  return typeof payload.message === 'string' ? payload.message : null
}

function codeFromStatus(status: number): AppErrorCode {
  if (STATUS_CODES[status]) {
    return STATUS_CODES[status]
  }

  if (status >= 500) {
    return 'SERVER_ERROR'
  }

  return 'UNKNOWN_ERROR'
}

function isTimeout(error: AxiosError): boolean {
  return (
    error.code === 'ECONNABORTED' ||
    error.code === 'ETIMEDOUT' ||
    /timeout/i.test(error.message)
  )
}

export function requestMethod(error: unknown): string {
  if (error instanceof AxiosError) {
    return (error.config?.method ?? 'get').toLowerCase()
  }

  return 'get'
}

export function requestUrl(error: unknown): string | undefined {
  if (error instanceof AxiosError) {
    return error.config?.url ?? error.config?.baseURL
  }

  return undefined
}

export function isRetryableQueryError(error: unknown): boolean {
  const normalized = normalizeError(error)
  if (!normalized.retryable) {
    return false
  }

  if (normalized.status === 500) {
    return requestMethod(error) === 'get'
  }

  return true
}

export function isUnauthorized(error: unknown): boolean {
  return normalizeError(error).status === 401
}

export function normalizeError(error: unknown): AppError {
  if (error instanceof AxiosError) {
    const status = error.response?.status ?? 0
    const data = error.response?.data
    const fields = fieldErrorsFrom(data)
    const firstField = Object.values(fields)[0]
    const backend = backendMessage(data)
    const requestId = requestIdFrom(error)

    if (!error.response) {
      const timeout = isTimeout(error)
      const code: AppErrorCode = timeout ? 'TIMEOUT' : 'NETWORK_ERROR'
      return {
        status: 0,
        code,
        message: FALLBACKS[code],
        retryable: true,
        requestId,
        fieldErrors: {},
        originalError: error,
      }
    }

    const declared = knownCode(
      data && typeof data === 'object' ? (data as { code?: unknown }).code : null
    )
    const code = declared ?? codeFromStatus(status)
    const fallback = FALLBACKS[code] ?? FALLBACKS.UNKNOWN_ERROR
    const retryable =
      status === 429 ||
      status === 502 ||
      status === 503 ||
      status === 504 ||
      status >= 500

    let message = fallback
    if (status === 422 || Object.keys(fields).length > 0) {
      message = firstField ?? safeUserText(backend, fallback)
    } else if (status === 403 || status === 409 || status === 404 || status === 400) {
      message = safeUserText(backend, fallback)
    } else if (status < 500) {
      message = safeUserText(backend, fallback)
    }

    return {
      status,
      code,
      message,
      retryable,
      requestId,
      fieldErrors: fields,
      originalError: error,
    }
  }

  if (error instanceof Error) {
    return {
      status: 0,
      code: 'UNKNOWN_ERROR',
      message: safeUserText(error.message, FALLBACKS.UNKNOWN_ERROR),
      retryable: false,
      requestId: null,
      fieldErrors: {},
      originalError: error,
    }
  }

  return {
    status: 0,
    code: 'UNKNOWN_ERROR',
    message: FALLBACKS.UNKNOWN_ERROR,
    retryable: false,
    requestId: null,
    fieldErrors: {},
    originalError: error,
  }
}
