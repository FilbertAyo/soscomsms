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
