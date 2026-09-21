import { AxiosError } from 'axios'
import { api } from '@/lib/api'
import { type AuthUser } from '@/types/auth'

export const meQueryKey = ['auth', 'me'] as const

export async function fetchMe(): Promise<AuthUser> {
  const { data } = await api.get<{ user: AuthUser }>('/api/me')
  return data.user
}

export type OtpChallenge = {
  requiresOtp: true
  challenge: string
  email: string
  expiresIn: number
}

export async function loginRequest(
  email: string,
  password: string,
  remember = false
): Promise<OtpChallenge> {
  const { data } = await api.post<OtpChallenge>('/api/login', {
    email,
    password,
    remember,
  })
  return data
}

export async function registerRequest(payload: {
  name: string
  email: string
  password: string
  password_confirmation: string
}): Promise<OtpChallenge> {
  const { data } = await api.post<OtpChallenge>('/api/register', payload)
  return data
}

export async function verifyOtpRequest(
  challenge: string,
  otp: string
): Promise<AuthUser> {
  const { data } = await api.post<{ user: AuthUser }>('/api/otp/verify', {
    challenge,
    otp,
  })
  return data.user
}

export async function resendOtpRequest(challenge: string): Promise<OtpChallenge> {
  const { data } = await api.post<OtpChallenge>('/api/otp/resend', {
    challenge,
  })
  return data
}

export async function logoutRequest(): Promise<void> {
  await api.post('/api/logout')
}

export async function forgotPasswordRequest(email: string): Promise<string> {
  const { data } = await api.post<{ message: string }>('/api/forgot-password', {
    email,
  })
  return data.message
}

export async function resetPasswordRequest(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<string> {
  const { data } = await api.post<{ message: string }>(
    '/api/reset-password',
    payload
  )
  return data.message
}

export async function updateProfileRequest(payload: {
  name?: string
  email?: string
  business_name?: string | null
  phone?: string | null
  job_title?: string | null
}): Promise<AuthUser> {
  const { data } = await api.patch<{ user: AuthUser }>('/api/profile', payload)
  return data.user
}

export async function updatePasswordRequest(payload: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<string> {
  const { data } = await api.put<{ message: string }>('/api/password', payload)
  return data.message
}

export function isUnauthorized(error: unknown): boolean {
  return error instanceof AxiosError && error.response?.status === 401
}

export function validationMessage(error: unknown, field: string): string | null {
  if (!(error instanceof AxiosError)) {
    return null
  }

  const messages = error.response?.data?.errors?.[field]
  if (Array.isArray(messages) && typeof messages[0] === 'string') {
    return messages[0]
  }

  if (typeof error.response?.data?.message === 'string') {
    return error.response.data.message
  }

  return null
}

export function applyValidationErrors(
  error: unknown,
  setError: (field: string, error: { message: string }) => void,
  fieldMap: Record<string, string> = {}
): string {
  if (!(error instanceof AxiosError)) {
    return 'Something went wrong. Try again.'
  }

  if (error.response?.status === 419) {
    return 'Your session expired. Refresh the page and try again.'
  }

  const errors = error.response?.data?.errors
  if (errors && typeof errors === 'object') {
    for (const [field, messages] of Object.entries(errors)) {
      if (Array.isArray(messages) && typeof messages[0] === 'string') {
        setError(fieldMap[field] ?? field, { message: messages[0] })
      }
    }
  }

  if (typeof error.response?.data?.message === 'string') {
    return error.response.data.message
  }

  return 'Something went wrong. Try again.'
}
