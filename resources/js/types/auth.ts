export const USER_ROLES = ['admin', 'customer'] as const

export type UserRole = (typeof USER_ROLES)[number]

export const CUSTOMER_STATUSES = [
  'pending_profile',
  'active',
  'suspended',
] as const

export type CustomerStatus = (typeof CUSTOMER_STATUSES)[number]

export type AuthCustomer = {
  id: number
  businessName: string | null
  phone: string | null
  jobTitle: string | null
  status: CustomerStatus
  smsBalance: number
}

export type AuthUser = {
  id: number
  name: string
  email: string
  role: UserRole
  customer: AuthCustomer | null
}

export function isAdmin(user: AuthUser): boolean {
  return user.role === 'admin'
}

export function extraNavForRole(role: UserRole): 'admin' | null {
  switch (role) {
    case 'admin':
      return 'admin'
    case 'customer':
      return null
    default: {
      const _exhaustive: never = role
      return _exhaustive
    }
  }
}

export function needsProfileCompletion(user: AuthUser): boolean {
  if (user.role !== 'customer') {
    return false
  }

  const status = user.customer?.status ?? 'pending_profile'

  switch (status) {
    case 'pending_profile':
      return true
    case 'active':
    case 'suspended':
      return false
    default: {
      const _exhaustive: never = status
      return _exhaustive
    }
  }
}

const PROFILE_LOCKED_PREFIXES = [
  '/send-sms',
  '/campaigns',
  '/reports',
  '/contacts',
  '/sender-ids',
  '/wallet',
  '/developer',
] as const

export function isProfileLockedPath(pathname: string): boolean {
  return PROFILE_LOCKED_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
  )
}

export function isTanzaniaMobile(value: string): boolean {
  const digits = value.replace(/\D+/g, '')
  let normalized = digits

  if (digits.startsWith('255')) {
    normalized = digits
  } else if (digits.startsWith('0')) {
    normalized = `255${digits.slice(1)}`
  } else if (digits.length === 9) {
    normalized = `255${digits}`
  }

  return /^255[67]\d{8}$/.test(normalized)
}
