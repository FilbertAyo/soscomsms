import { read, utils, type CellObject, type WorkSheet } from 'xlsx'
import { parseCsvText } from '@/lib/csv'

export const SPREADSHEET_ACCEPT =
  '.csv,.txt,.xlsx,.xls,text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

export async function parseSpreadsheetFile(file: File): Promise<string[][]> {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''

  if (extension === 'csv' || extension === 'txt') {
    return parseCsvText(await file.text())
  }

  if (extension !== 'xlsx' && extension !== 'xls') {
    throw new Error('Upload a CSV or Excel file.')
  }

  const workbook = read(await file.arrayBuffer(), {
    type: 'array',
    cellDates: false,
  })
  const sheetName = workbook.SheetNames[0]
  if (!sheetName) {
    return []
  }

  const sheet = workbook.Sheets[sheetName]
  if (!sheet) {
    return []
  }

  return worksheetRows(sheet)
}

function worksheetRows(sheet: WorkSheet): string[][] {
  const ref = sheet['!ref']
  if (!ref) {
    return []
  }

  const range = utils.decode_range(ref)
  const rows: string[][] = []

  for (let rowIndex = range.s.r; rowIndex <= range.e.r; rowIndex += 1) {
    const row: string[] = []
    for (let columnIndex = 0; columnIndex <= range.e.c; columnIndex += 1) {
      const address = utils.encode_cell({ r: rowIndex, c: columnIndex })
      row.push(cellText(sheet[address] as CellObject | undefined))
    }

    if (row.some((value) => value.trim() !== '')) {
      rows.push(row)
    }
  }

  return rows
}

function cellText(cell: CellObject | undefined): string {
  if (!cell || cell.t === 'e') {
    return ''
  }

  const value = cell.v
  if (value === undefined || value === null || value instanceof Date) {
    return ''
  }

  if (typeof value === 'number') {
    if (!Number.isFinite(value)) {
      return ''
    }

    const rounded = Math.round(value)
    if (Math.abs(value - rounded) < 1e-6) {
      return String(rounded)
    }

    return String(value)
  }

  if (typeof value === 'boolean') {
    return value ? '1' : '0'
  }

  return String(value).trim()
}
