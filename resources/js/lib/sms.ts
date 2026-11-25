import { tanzaniaHour } from '@/lib/format'

const GSM_BASIC_CHARS =
  '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#$¤%&\'()*+,-./0123456789:;<=>?' +
  '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'

const GSM_BASIC = new Set([...GSM_BASIC_CHARS])
const GSM_EXTENDED = new Set(['|', '^', '€', '{', '}', '[', ']', '~', '\\'])

export type SmsEncoding = 'gsm' | 'unicode'

export type SmsAnalysis = {
  encoding: SmsEncoding
  characterCount: number
  segmentCount: number
  singleLimit: number
  concatLimit: number
}

export function analyzeSms(message: string): SmsAnalysis {
  const gsmLength = gsmSeptetLength(message)
  if (gsmLength !== null) {
    return pack('gsm', gsmLength, 160, 153)
  }

  return pack('unicode', unicodeLength(message), 70, 63)
}

export function countSmsSegments(message: string) {
  return analyzeSms(message).segmentCount
}

export function estimateSmsCost(
  recipients: number,
  message: string,
  unitPriceTzs = 30
) {
  const analysis = analyzeSms(message)
  const totalSms = recipients * analysis.segmentCount
  return {
    ...analysis,
    totalSms,
    costTzs: totalSms * unitPriceTzs,
  }
}

export function parsePhoneNumbers(value: string) {
  return value
    .split(/[\n,;]+/)
    .map((item) => item.trim())
    .filter(Boolean)
}

export const NAME_PLACEHOLDER = '{name}'
export const NAME_EXAMPLE = 'Amina'

const PHONE_HEADERS = new Set([
  'phone',
  'phones',
  'phone number',
  'number',
  'numbers',
  'mobile',
  'mobile number',
  'msisdn',
])

const NAME_HEADERS = new Set(['name', 'names', 'jina', 'fullname', 'full name'])

export type NamedRecipient = {
  phone: string
  name: string
}

export function messageUsesName(body: string) {
  return body.includes(NAME_PLACEHOLDER)
}

export function cleanRecipientName(name: string) {
  const cleaned = name
    .split(NAME_PLACEHOLDER)
    .join('')
    .replace(/\s+/g, ' ')
    .trim()

  if (!/\p{L}/u.test(cleaned)) {
    return ''
  }

  return Array.from(cleaned).slice(0, 120).join('')
}

export function renderNamedMessage(body: string, name: string) {
  return body.split(NAME_PLACEHOLDER).join(cleanRecipientName(name))
}

export function insertTextAtCursor(
  value: string,
  token: string,
  selectionStart: number | null,
  selectionEnd: number | null
) {
  const start = selectionStart ?? value.length
  const end = selectionEnd ?? value.length
  const next = value.slice(0, start) + token + value.slice(end)

  return {
    value: next,
    caret: start + token.length,
  }
}

export function parseNamedRecipients(value: string): NamedRecipient[] {
  return value
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map(parseNamedLine)
}

export function parseUploadRecipients(rows: string[][]): NamedRecipient[] {
  if (rows.length === 0) {
    return []
  }

  const header = rows[0].map((cell) => cell.trim().toLowerCase())
  const phoneHeader = header.findIndex((cell) => PHONE_HEADERS.has(cell))
  const nameHeader = header.findIndex((cell) => NAME_HEADERS.has(cell))
  const hasHeader = phoneHeader >= 0 || nameHeader >= 0
  const phoneIndex = phoneHeader >= 0 ? phoneHeader : 0
  const nameIndex = nameHeader >= 0 ? nameHeader : 1
  const data = hasHeader ? rows.slice(1) : rows
  const people: NamedRecipient[] = []

  for (const row of data) {
    const phoneCell = (row[phoneIndex] ?? '').trim()
    const nameCell = (row[nameIndex] ?? '').trim()
    if (phoneCell === '' && nameCell === '') {
      continue
    }

    let phone = phoneCell
    let name = row.length < 2 && !hasHeader ? '' : cleanRecipientName(nameCell)
    if (!looksLikePhone(phone) && looksLikePhone(nameCell)) {
      phone = nameCell
      name = cleanRecipientName(phoneCell)
    }

    if (phone === '') {
      continue
    }

    people.push({ phone, name })
  }

  return people
}

function parseNamedLine(line: string): NamedRecipient {
  const separated = line.match(/^(.+?)[,;\t]\s*(.*)$/)
  let phone = line
  let name = ''

  if (separated) {
    phone = separated[1].trim()
    name = separated[2] ?? ''
  } else {
    const parts = line.split(/\s+/)
    if (parts.length >= 2) {
      phone = parts[0] ?? line
      name = parts.slice(1).join(' ')
    }
  }

  if (!looksLikePhone(phone) && looksLikePhone(name)) {
    const swappedPhone = name.trim()
    const swappedName = phone
    phone = swappedPhone
    name = swappedName
  }

  return {
    phone: phone.trim(),
    name: cleanRecipientName(name),
  }
}

function looksLikePhone(value: string) {
  const digits = value.replace(/\D/g, '')
  return digits.length >= 9 && digits.length <= 15 && !/\p{L}/u.test(value)
}

export function getGreeting(date = new Date()) {
  const hour = tanzaniaHour(date)
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
}

function gsmSeptetLength(message: string): number | null {
  let length = 0
  for (const character of message) {
    if (GSM_BASIC.has(character)) {
      length += 1
      continue
    }
    if (GSM_EXTENDED.has(character)) {
      length += 2
      continue
    }
    return null
  }
  return length
}

function unicodeLength(message: string): number {
  let length = 0
  for (const character of message) {
    const codePoint = character.codePointAt(0) ?? 0
    length += codePoint > 0xffff ? 2 : 1
  }
  return length
}

function pack(
  encoding: SmsEncoding,
  characterCount: number,
  singleLimit: number,
  concatLimit: number
): SmsAnalysis {
  const segmentCount =
    characterCount === 0
      ? 0
      : characterCount <= singleLimit
        ? 1
        : Math.ceil(characterCount / concatLimit)

  return {
    encoding,
    characterCount,
    segmentCount,
    singleLimit,
    concatLimit,
  }
}
