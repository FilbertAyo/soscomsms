import axios, { type AxiosError, type AxiosRequestConfig, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'

const XSRF_COOKIE = 'XSRF-TOKEN'

declare module 'axios' {
  export interface AxiosRequestConfig {
    _csrfRetry?: boolean
  }
}

function metaCsrfToken(): string | null {
  return (
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
    null
  )
}

function hasXsrfCookie(): boolean {
  return document.cookie
    .split(';')
    .some((part) => part.trim().startsWith(`${XSRF_COOKIE}=`))
}

export function setCsrfToken(token: string): void {
  const meta = document.querySelector('meta[name="csrf-token"]')
  if (meta) {
    meta.setAttribute('content', token)
  }
}

function syncCsrfFromResponse(response: AxiosResponse): void {
  const header = response.headers['x-csrf-token']
  if (typeof header === 'string' && header !== '') {
    setCsrfToken(header)
  }

  const bodyToken = response.data?.csrf_token
  if (typeof bodyToken === 'string' && bodyToken !== '') {
    setCsrfToken(bodyToken)
  }
}

function dropStaleCsrfHeader(config: InternalAxiosRequestConfig): void {
  const headers = config.headers

  if (typeof headers.delete === 'function') {
    headers.delete('X-CSRF-TOKEN')
    return
  }

  delete headers['X-CSRF-TOKEN']
}

const csrfClient = axios.create({
  baseURL: '/',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export async function refreshCsrfCookie(): Promise<void> {
  const response = await csrfClient.get('/sanctum/csrf-cookie')
  syncCsrfFromResponse(response)
}

export const api = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: XSRF_COOKIE,
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()
  const mutating = ['post', 'put', 'patch', 'delete'].includes(method)

  if (mutating && !hasXsrfCookie()) {
    await refreshCsrfCookie()
  }

  if (hasXsrfCookie()) {
    dropStaleCsrfHeader(config)
  } else {
    const token = metaCsrfToken()
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token
    }
  }

  return config
})

api.interceptors.response.use(
  (response) => {
    syncCsrfFromResponse(response)
    return response
  },
  async (error: AxiosError) => {
    if (error.response) {
      syncCsrfFromResponse(error.response)
    }

    const status = error.response?.status
    const original = error.config as AxiosRequestConfig | undefined

    if (status === 419 && original && !original._csrfRetry) {
      original._csrfRetry = true
      await refreshCsrfCookie()
      return api.request(original)
    }

    return Promise.reject(error)
  }
)
