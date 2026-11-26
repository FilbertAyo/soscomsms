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

function dateTimeParts(value: Date) {
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: APP_TIMEZONE,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(value)
}

function part(
  parts: Intl.DateTimeFormatPart[],
  type: Intl.DateTimeFormatPartTypes
) {
  return parts.find((item) => item.type === type)?.value ?? ''
}

/** Calendar date in the app timezone, as yyyy-mm-dd for date inputs. */
export function appDateInputValue(date = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: APP_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date)

  return `${part(parts, 'year')}-${part(parts, 'month')}-${part(parts, 'day')}`
}

/** yyyy-mm-dd that many calendar days before today in the app timezone. */
export function appDateDaysAgo(days: number) {
  const [year, month, day] = appDateInputValue().split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day))
  date.setUTCDate(date.getUTCDate() - days)
  const nextYear = date.getUTCFullYear()
  const nextMonth = String(date.getUTCMonth() + 1).padStart(2, '0')
  const nextDay = String(date.getUTCDate()).padStart(2, '0')

  return `${nextYear}-${nextMonth}-${nextDay}`
}

export function formatDateTime(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  const parts = dateTimeParts(date)

  return `${part(parts, 'day')} ${part(parts, 'month')} ${part(parts, 'year')}, ${part(parts, 'hour')}:${part(parts, 'minute')}`
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
