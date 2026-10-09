import type { jsPDF } from 'jspdf'
import { webhooksVisible } from '@/lib/features'

const pageWidth = 210
const pageHeight = 297
const marginX = 18
const marginTop = 18
const marginBottom = 18
const contentWidth = pageWidth - marginX * 2
const brandGreen: [number, number, number] = [55, 144, 25]
const ink: [number, number, number] = [22, 22, 22]
const muted: [number, number, number] = [90, 90, 90]
const rule: [number, number, number] = [220, 220, 220]
const productionApiBase = 'https://sms.soscom.co.tz/api/v1'

type PdfDoc = InstanceType<typeof jsPDF>

export async function downloadApiDocsPdf(): Promise<void> {
  const { jsPDF: JsPDF } = await import('jspdf')
  const logo = await loadLogoDataUrl()
  const doc = new JsPDF({ unit: 'mm', format: 'a4' })
  const writer = new PdfWriter(doc, logo)

  writer.cover()
  writer.heading('1. Introduction')
  writer.body(
    'Soscom SMS is a JSON API for Tanzanian mobile numbers only (+255 / 07…). Create a key on the API keys page, then call the endpoints below. Your customers and servers do not need an IP whitelist. Soscom sends from its own server after the upstream provider allows that address.'
  )
  writer.label('Base URL')
  writer.mono(productionApiBase)
  writer.body(
    'Credits are deducted when Soscom accepts the send into its queue — the same as the dashboard. A gateway reject is refunded. A timeout is left as unknown (no retry, no refund). A later delivery failure is not refunded.'
  )

  writer.heading('2. Authentication')
  writer.body('Send the full API key on every request. It is shown once when you create it. Use either header:')
  writer.code(`Authorization: Bearer sk_live_••••••••••••••••
X-Api-Key: sk_live_••••••••••••••••`)
  writer.bullets([
    '60 requests per minute per API key.',
    'Optional Idempotency-Key on send and bulk. Same key and body returns the first response. Same key and a different body returns 409.',
    'Every response includes X-Request-Id. You may send your own; otherwise Soscom creates one.',
    'Revoked keys and missing keys return 401.',
    'Suspended or incomplete accounts return 403.',
  ])

  writer.heading('3. Send SMS')
  writer.endpoint('POST', '/api/v1/sms/send')
  writer.body(
    'to may be one Tanzanian number or an array. sender_id must be an active Sender ID on this account. Optional name labels the campaign. GSM messages use 160 characters (153 when concatenated). Unicode uses 70 (63 concatenated).'
  )
  writer.label('cURL')
  writer.code(`curl ${productionApiBase}/sms/send \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Idempotency-Key: fee-reminder-001" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"sender_id":"MWALIKO","to":"+255712445221","message":"Fee reminder"}'`)
  writer.label('201 response')
  writer.code(`{
  "id": 21,
  "name": "Fee reminder",
  "status": "queued",
  "sender_id": "MWALIKO",
  "message": "Fee reminder",
  "encoding": "gsm",
  "character_count": 13,
  "segments": 1,
  "recipients": 1,
  "sent": 0,
  "delivered": 0,
  "failed": 0,
  "sms": 1,
  "sms_balance": 19,
  "messages": [
    {
      "id": 41,
      "campaign_id": 21,
      "to": "+255712445221",
      "status": "queued",
      "segments": 1,
      "provider_message_id": null,
      "delivered_at": null
    }
  ]
}`)

  writer.heading('4. Bulk SMS')
  writer.endpoint('POST', '/api/v1/sms/bulk')
  writer.body(
    'to must be an array of Tanzanian numbers (up to 5,000). Cost, Sender ID rules, and the credit ledger match a dashboard campaign.'
  )
  writer.code(`curl ${productionApiBase}/sms/bulk \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Idempotency-Key: fee-reminder-bulk-001" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"sender_id":"MWALIKO","to":["+255712445221","+255754221890"],"message":"Fee reminder"}'`)

  writer.heading('5. Balance')
  writer.endpoint('GET', '/api/v1/balance')
  writer.body('Returns remaining SMS credits for the key’s account.')
  writer.code(`curl ${productionApiBase}/balance \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Accept: application/json"

{ "sms_balance": 10104 }`)

  writer.heading('6. Sender IDs')
  writer.endpoint('GET', '/api/v1/sender-ids')
  writer.body(
    'Lists Sender IDs you can send with, including shared Soscom names. Pending requests cannot be used. Another account’s Sender ID returns 403.'
  )
  writer.code(`GET ${productionApiBase}/sender-ids

{
  "sender_ids": [
    { "id": 1, "name": "HARUSI", "status": "active", "source": "generic" }
  ]
}`)

  writer.heading('7. Message status')
  writer.endpoint('GET', '/api/v1/sms/{id}')
  writer.endpoint('GET', '/api/v1/messages/{id}')
  writer.body(
    'Campaign id comes from the send response. Recipient id is each item in messages. Status values: pending, queued, sent, delivered, failed, rejected, unknown.'
  )
  writer.code(`GET ${productionApiBase}/sms/21
GET ${productionApiBase}/messages/41`)

  writer.heading('8. Delivery reports')
  writer.endpoint('GET', '/api/v1/reports')
  writer.body(
    'Lists deliveries for this account. Filter with campaign_id, id (recipient id), to, or q. Paginate with per_page (max 200).'
  )
  writer.code(`GET ${productionApiBase}/reports?to=+255712445221

{
  "reports": [
    {
      "id": 41,
      "to": "+255712445221",
      "campaign_id": 21,
      "campaign_name": "Fee reminder",
      "sender_id": "MWALIKO",
      "message": "Fee reminder",
      "status": "delivered",
      "provider_message_id": "FAKE-41",
      "sent_at": "2026-09-20T15:01:00+03:00",
      "delivered_at": "2026-09-20T15:01:00+03:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}`)

  if (webhooksVisible) {
    writer.heading('9. Webhooks')
    writer.body(
      'Create a webhook on the Webhooks page. Soscom POSTs JSON for delivery.updated and campaign.completed. Production URLs must be HTTPS.'
    )
    writer.body(
      'Headers: X-Soscom-Event, X-Soscom-Delivery-Id, X-Soscom-Signature: t=TIMESTAMP,v1=HEX. Sign TIMESTAMP + "." + raw_body with HMAC-SHA256 and your webhook secret. Failed deliveries are retried with backoff.'
    )
    writer.code(`{
  "id": "evt_01h…",
  "type": "delivery.updated",
  "created_at": "2026-09-20T15:01:00+03:00",
  "data": {
    "id": 41,
    "campaign_id": 21,
    "to": "+255712445221",
    "status": "delivered",
    "sender_id": "MWALIKO",
    "delivered_at": "2026-09-20T15:01:00+03:00"
  }
}`)
  }

  writer.heading(webhooksVisible ? '10. Error codes' : '9. Error codes')
  writer.errorRow('401', 'Missing, invalid, or revoked API key (invalid_api_key).')
  writer.errorRow('402', 'Not enough SMS credits.')
  writer.errorRow('403', 'Sender ID is not on this account, or the account is suspended / inactive.')
  writer.errorRow('404', 'Campaign or message not found.')
  writer.errorRow('409', 'Idempotency-Key already used with a different body.')
  writer.errorRow('422', 'Invalid Tanzanian number, empty message, or bulk to was not an array.')
  writer.errorRow('429', 'More than 60 requests in a minute for this key.')

  writer.finishPages()
  doc.save('Soscom-SMS-API-Documentation.pdf')
}

class PdfWriter {
  private y = marginTop

  constructor(
    private readonly doc: PdfDoc,
    private readonly logo: string | null
  ) {}

  cover(): void {
    this.header()
    this.doc.setFont('helvetica', 'bold')
    this.doc.setFontSize(18)
    this.doc.setTextColor(...ink)
    this.doc.text('API Documentation', marginX, this.y)
    this.y += 7
    this.doc.setFont('helvetica', 'normal')
    this.doc.setFontSize(10)
    this.doc.setTextColor(...muted)
    const intro = this.doc.splitTextToSize(
      'Send Tanzanian SMS from your own application. Developers talk only to Soscom SMS, never to the upstream gateway.',
      contentWidth
    ) as string[]
    this.doc.text(intro, marginX, this.y)
    this.y += intro.length * 5 + 4
    this.rule()
  }

  heading(text: string): void {
    this.ensure(16)
    this.y += 4
    this.doc.setFont('helvetica', 'bold')
    this.doc.setFontSize(13)
    this.doc.setTextColor(...brandGreen)
    this.doc.text(text, marginX, this.y)
    this.y += 7
  }

  label(text: string): void {
    this.ensure(8)
    this.doc.setFont('helvetica', 'bold')
    this.doc.setFontSize(8)
    this.doc.setTextColor(...muted)
    this.doc.text(text.toUpperCase(), marginX, this.y)
    this.y += 5
  }

  body(text: string): void {
    this.doc.setFont('helvetica', 'normal')
    this.doc.setFontSize(10)
    this.doc.setTextColor(...ink)
    const lines = this.doc.splitTextToSize(text, contentWidth) as string[]
    this.writeLines(lines, 5)
    this.y += 2
  }

  bullets(items: string[]): void {
    this.doc.setFont('helvetica', 'normal')
    this.doc.setFontSize(10)
    this.doc.setTextColor(...ink)
    for (const item of items) {
      const lines = this.doc.splitTextToSize(item, contentWidth - 6) as string[]
      this.ensure(lines.length * 5 + 1)
      this.doc.text('•', marginX, this.y)
      this.doc.text(lines, marginX + 5, this.y)
      this.y += lines.length * 5 + 1
    }
    this.y += 2
  }

  endpoint(method: string, path: string): void {
    this.ensure(8)
    this.doc.setFont('helvetica', 'bold')
    this.doc.setFontSize(9)
    this.doc.setTextColor(...brandGreen)
    this.doc.text(method, marginX, this.y)
    const methodWidth = this.doc.getTextWidth(method)
    this.doc.setFont('courier', 'normal')
    this.doc.setTextColor(...ink)
    this.doc.text(path, marginX + methodWidth + 3, this.y)
    this.y += 6
  }

  mono(text: string): void {
    this.doc.setFont('courier', 'normal')
    this.doc.setFontSize(9)
    this.doc.setTextColor(...ink)
    const lines = this.doc.splitTextToSize(text, contentWidth) as string[]
    this.writeLines(lines, 4.5)
    this.y += 2
  }

  code(text: string): void {
    this.doc.setFont('courier', 'normal')
    this.doc.setFontSize(8)
    const lineHeight = 4.2
    const wrapped: string[] = []
    for (const line of text.split('\n')) {
      wrapped.push(
        ...(this.doc.splitTextToSize(line.length === 0 ? ' ' : line, contentWidth - 8) as string[])
      )
    }

    const drawChunk = (start: number, count: number, y: number) => {
      const height = count * lineHeight + 6
      this.doc.setFillColor(246, 246, 246)
      this.doc.setDrawColor(...rule)
      this.doc.rect(marginX, y - 4, contentWidth, height, 'FD')
      this.doc.setTextColor(...ink)
      for (let i = 0; i < count; i += 1) {
        this.doc.text(wrapped[start + i] ?? '', marginX + 4, y + i * lineHeight)
      }
      return height
    }

    let index = 0
    while (index < wrapped.length) {
      const remaining = pageHeight - marginBottom - this.y
      const maxLines = Math.max(1, Math.floor((remaining - 6) / lineHeight))
      if (maxLines < 2 && this.y > marginTop + 24) {
        this.addPage()
        continue
      }
      const count = Math.min(wrapped.length - index, maxLines)
      const height = drawChunk(index, count, this.y)
      this.y += height + 3
      index += count
      if (index < wrapped.length) {
        this.addPage()
      }
    }
    this.y += 1
  }

  errorRow(code: string, detail: string): void {
    this.doc.setFont('helvetica', 'bold')
    this.doc.setFontSize(10)
    const lines = this.doc.splitTextToSize(detail, contentWidth - 14) as string[]
    this.ensure(lines.length * 5 + 1)
    this.doc.setTextColor(...brandGreen)
    this.doc.text(code, marginX, this.y)
    this.doc.setFont('helvetica', 'normal')
    this.doc.setTextColor(...ink)
    this.doc.text(lines, marginX + 14, this.y)
    this.y += lines.length * 5 + 1
  }

  finishPages(): void {
    const total = this.doc.getNumberOfPages()
    for (let i = 1; i <= total; i += 1) {
      this.doc.setPage(i)
      this.doc.setFont('helvetica', 'normal')
      this.doc.setFontSize(8)
      this.doc.setTextColor(...muted)
      this.doc.text('sms.soscom.co.tz  ·  SOSCOM Technologies', marginX, pageHeight - 10)
      this.doc.text(`${i} / ${total}`, pageWidth - marginX, pageHeight - 10, { align: 'right' })
    }
  }

  private header(): void {
    this.doc.setFillColor(0, 0, 0)
    this.doc.rect(0, 0, pageWidth, 24, 'F')
    if (this.logo) {
      this.doc.addImage(this.logo, 'PNG', marginX, 4, 48, 16)
    } else {
      this.doc.setFont('helvetica', 'bold')
      this.doc.setFontSize(14)
      this.doc.setTextColor(255, 255, 255)
      this.doc.text('soscom', marginX, 15)
      const soscomWidth = this.doc.getTextWidth('soscom')
      this.doc.setTextColor(...brandGreen)
      this.doc.text('sms', marginX + soscomWidth, 15)
    }
    this.doc.setFont('helvetica', 'normal')
    this.doc.setFontSize(9)
    this.doc.setTextColor(180, 180, 180)
    this.doc.text('API Documentation', pageWidth - marginX, 14, { align: 'right' })
    this.y = 34
  }

  private rule(): void {
    this.doc.setDrawColor(...rule)
    this.doc.setLineWidth(0.3)
    this.doc.line(marginX, this.y, pageWidth - marginX, this.y)
    this.y += 8
  }

  private writeLines(lines: string[], lineHeight: number): void {
    for (const line of lines) {
      this.ensure(lineHeight)
      this.doc.text(line, marginX, this.y)
      this.y += lineHeight
    }
  }

  private ensure(needed: number): void {
    if (this.y + needed > pageHeight - marginBottom) {
      this.addPage()
    }
  }

  private addPage(): void {
    this.doc.addPage()
    this.y = marginTop
    this.header()
    this.rule()
  }
}

async function loadLogoDataUrl(): Promise<string | null> {
  try {
    const response = await fetch('/images/soscom-sms-wordmark.png')
    if (!response.ok) {
      return null
    }
    const blob = await response.blob()
    return await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(String(reader.result))
      reader.onerror = () => reject(reader.error)
      reader.readAsDataURL(blob)
    })
  } catch {
    return null
  }
}
