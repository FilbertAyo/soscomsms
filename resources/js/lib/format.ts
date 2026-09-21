export const APP_TIMEZONE = 'Africa/Dar_es_Salaam'

export function formatTzMobile(phone: string) {
  const digits = phone.replace(/\D+/g, '')

  if (digits.startsWith('255') && digits.length === 12) {
    return `0${digits.slice(3)}`
  }

  return phone
}

export function formatNumber(value: number) {
  return value.toLocaleString('en-TZ')
}

export function formatTZS(value: number) {
  return `TZS ${Math.abs(value).toLocaleString('en-TZ')}`
}

export function formatDate(value: string) {
  return new Date(value).toLocaleDateString('en-GB', {
    timeZone: APP_TIMEZONE,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function formatDateTime(value: string) {
  return new Date(value).toLocaleString('en-GB', {
    timeZone: APP_TIMEZONE,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZoneName: 'short',
  })
}

export function firstName(fullName: string) {
  return fullName.split(' ')[0] ?? fullName
}

export function tanzaniaHour(date = new Date()) {
  const hour = new Intl.DateTimeFormat('en-GB', {
    timeZone: APP_TIMEZONE,
    hour: 'numeric',
    hourCycle: 'h23',
  }).format(date)

  return Number.parseInt(hour, 10)
}
