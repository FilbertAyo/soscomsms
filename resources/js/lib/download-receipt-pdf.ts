import type { jsPDF } from 'jspdf'
import { formatNumber, formatTZS } from '@/lib/format'
import { type PurchaseReceipt } from '@/types/payments'

const pageWidth = 210
const marginX = 16
const contentWidth = pageWidth - marginX * 2
const brandGreen: [number, number, number] = [55, 144, 25]
const ink: [number, number, number] = [22, 22, 22]
const muted: [number, number, number] = [90, 90, 90]
const rule: [number, number, number] = [220, 220, 220]
const paper: [number, number, number] = [247, 248, 246]
const reversed: [number, number, number] = [168, 42, 42]

type PdfDoc = InstanceType<typeof jsPDF>

export async function downloadReceiptPdf(receipt: PurchaseReceipt): Promise<void> {
  const { jsPDF: JsPDF } = await import('jspdf')
  const logo = await loadLogoDataUrl()
  const doc = new JsPDF({ unit: 'mm', format: 'a4' })
  let y = 18

  y = drawHeader(doc, logo, y)
  y = drawMeta(doc, receipt, y)
  y = drawBillTo(doc, receipt, y)
  y = drawLine(doc, receipt, y)
  y = drawPayment(doc, receipt, y)
  drawTotal(doc, receipt, y)
  drawFooter(doc, receipt)

  const safeNumber = receipt.receiptNumber.replace(/[^\w.-]+/g, '-')
  doc.save(`Soscom-SMS-Receipt-${safeNumber}.pdf`)
}

function drawHeader(doc: PdfDoc, logo: string | null, y: number): number {
  const iconSize = 12
  let textX = marginX
  if (logo) {
    doc.addImage(logo, 'PNG', marginX, y - 8, iconSize, iconSize)
    textX = marginX + iconSize + 3
  }

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(20)
  doc.setTextColor(...ink)
  doc.text('soscom', textX, y)
  const wordmark = doc.getTextWidth('soscom')
  doc.setTextColor(...brandGreen)
  doc.text('sms', textX + wordmark, y)

  doc.setFontSize(16)
  doc.text('RECEIPT', pageWidth - marginX, y, { align: 'right' })

  y += 6
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  doc.setTextColor(...ink)
  doc.text('SOSCOM Technologies Company Limited', textX, y)
  y += 4.5
  doc.setTextColor(...muted)
  doc.text('sms.soscom.co.tz  ·  sms@soscom.co.tz', textX, y)
  y += 5

  doc.setDrawColor(...brandGreen)
  doc.setLineWidth(0.8)
  doc.line(marginX, y, pageWidth - marginX, y)
  return y + 8
}

function drawMeta(doc: PdfDoc, receipt: PurchaseReceipt, y: number): number {
  const boxHeight = 18
  doc.setFillColor(...paper)
  doc.setDrawColor(...rule)
  doc.setLineWidth(0.2)
  doc.roundedRect(marginX, y, contentWidth, boxHeight, 1.5, 1.5, 'FD')

  const statusColor: [number, number, number] =
    receipt.status === 'reversed' ? reversed : brandGreen
  const status = receipt.status === 'reversed' ? 'REVERSED' : 'PAID'
  const colWidth = contentWidth / 3
  const columns: Array<[string, string, [number, number, number]]> = [
    ['RECEIPT NUMBER', receipt.receiptNumber, ink],
    ['DATE', receipt.issuedAtLabel ?? '—', ink],
    ['STATUS', status, statusColor],
  ]

  columns.forEach(([label, value, color], index) => {
    const x = marginX + 6 + index * colWidth
    const maxWidth = colWidth - 10
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(...muted)
    doc.text(label, x, y + 6)
    doc.setFont('helvetica', 'bold')
    doc.setTextColor(...color)
    let size = 11
    doc.setFontSize(size)
    while (size > 7 && doc.getTextWidth(value) > maxWidth) {
      size -= 0.5
      doc.setFontSize(size)
    }
    doc.text(value, x, y + 12.5)
  })

  return y + boxHeight + 8
}

function drawBillTo(doc: PdfDoc, receipt: PurchaseReceipt, y: number): number {
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(8)
  doc.setTextColor(...brandGreen)
  doc.text('BILLED TO', marginX, y)
  y += 6

  const lines = [
    receipt.billTo.name,
    receipt.billTo.businessName,
    receipt.billTo.email,
    receipt.billTo.phone,
  ].filter((line): line is string => Boolean(line && line.trim()))

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(11)
  doc.setTextColor(...ink)
  if (lines.length === 0) {
    doc.setTextColor(...muted)
    doc.text('—', marginX, y)
    return y + 10
  }

  lines.forEach((line, index) => {
    doc.setFont('helvetica', index === 0 ? 'bold' : 'normal')
    doc.text(line, marginX, y)
    y += 5
  })

  return y + 4
}

function drawLine(doc: PdfDoc, receipt: PurchaseReceipt, y: number): number {
  const rowHeight = 10
  doc.setFillColor(...brandGreen)
  doc.rect(marginX, y, contentWidth, rowHeight, 'F')
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(8)
  doc.setTextColor(255, 255, 255)
  doc.text('DESCRIPTION', marginX + 3, y + 6.5)
  doc.text('QTY', marginX + 92, y + 6.5, { align: 'right' })
  doc.text('UNIT PRICE', marginX + 128, y + 6.5, { align: 'right' })
  doc.text('AMOUNT', pageWidth - marginX - 3, y + 6.5, { align: 'right' })
  y += rowHeight

  const line = receipt.line
  doc.setFillColor(255, 255, 255)
  doc.setDrawColor(...rule)
  doc.rect(marginX, y, contentWidth, 12, 'S')
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(11)
  doc.setTextColor(...ink)
  doc.text(line.description, marginX + 3, y + 8)
  doc.setFont('helvetica', 'normal')
  doc.text(formatNumber(line.quantity), marginX + 92, y + 8, { align: 'right' })
  doc.text(formatTZS(line.unitPriceTzs), marginX + 128, y + 8, { align: 'right' })
  doc.setFont('helvetica', 'bold')
  doc.text(formatTZS(line.amountTzs), pageWidth - marginX - 3, y + 8, { align: 'right' })

  return y + 18
}

function drawPayment(doc: PdfDoc, receipt: PurchaseReceipt, y: number): number {
  const rows: Array<[string, string]> = [
    ['Payment method', receipt.paymentMethodLabel],
    ['Reference', receipt.internalReference || '—'],
  ]

  const transactionId = receipt.transactionId?.trim()
  if (transactionId) {
    rows.splice(1, 0, ['Transaction ID', transactionId])
  }

  if (receipt.payerReference) {
    rows.push(['Paid from', receipt.payerReference])
  }

  rows.forEach(([label, value]) => {
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.setTextColor(...muted)
    doc.text(label, marginX, y)
    doc.setTextColor(...ink)
    const lines = doc.splitTextToSize(value, contentWidth - 48) as string[]
    doc.text(lines, marginX + 42, y)
    y += Math.max(6, lines.length * 4.5)
  })

  return y + 4
}

function drawTotal(doc: PdfDoc, receipt: PurchaseReceipt, y: number): void {
  const boxHeight = 32
  doc.setFillColor(...paper)
  doc.roundedRect(marginX, y, contentWidth, boxHeight, 1.5, 1.5, 'F')
  doc.setFillColor(...brandGreen)
  doc.rect(marginX, y, 1.6, boxHeight, 'F')

  const rows: Array<[string, string, boolean]> = [
    ['SMS added', formatNumber(receipt.smsAdded), false],
    ['Balance after', receipt.balanceAfter == null ? '—' : formatNumber(receipt.balanceAfter), false],
    ['Amount paid', formatTZS(receipt.line.amountTzs), true],
  ]

  rows.forEach(([label, value, emphasis], index) => {
    const rowY = y + 8 + index * 8
    doc.setFont('helvetica', emphasis ? 'bold' : 'normal')
    doc.setFontSize(emphasis ? 12 : 10)
    doc.setTextColor(...(emphasis ? ink : muted))
    doc.text(label, marginX + 6, rowY)
    doc.setTextColor(...(emphasis ? brandGreen : ink))
    doc.text(value, pageWidth - marginX - 6, rowY, { align: 'right' })
  })
}

function drawFooter(doc: PdfDoc, receipt: PurchaseReceipt): void {
  const y = 272
  doc.setDrawColor(...rule)
  doc.setLineWidth(0.3)
  doc.line(marginX, y, pageWidth - marginX, y)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(8)
  doc.setTextColor(...muted)
  const proof =
    receipt.status === 'reversed'
      ? 'This purchase was reversed. This document is the record of the original SMS credit purchase.'
      : 'This document is proof of an SMS credit purchase.'
  doc.text(proof, marginX, y + 6)
  doc.text(
    `${receipt.issuer.website}  ·  ${receipt.issuer.company}`,
    marginX,
    y + 11
  )
}

async function loadLogoDataUrl(): Promise<string | null> {
  try {
    const response = await fetch('/images/soscom-sms-icon.png')
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
