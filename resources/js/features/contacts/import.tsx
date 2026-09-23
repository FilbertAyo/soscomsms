import { useMemo, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  contactGroupsQueryKey,
  importContacts,
} from '@/lib/contacts'
import { parseCsvText } from '@/lib/csv'
import { formatNumber } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Stepper } from '@/components/soscom/stepper'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { type ContactImportResult } from '@/types/contacts'

const steps = [
  { id: 'upload', label: 'Upload file' },
  { id: 'match', label: 'Match columns' },
  { id: 'review', label: 'Review contacts' },
]

const IGNORE = 'Ignore'

function guessColumn(headers: string[], candidates: string[]): string {
  const lowered = headers.map((header) => header.trim().toLowerCase())
  for (const candidate of candidates) {
    const index = lowered.indexOf(candidate)
    if (index >= 0) {
      return headers[index] ?? IGNORE
    }
  }

  return headers[0] ?? IGNORE
}

function looksLikePhone(value: string): boolean {
  const digits = value.replace(/\D/g, '')
  return /^(?:0[67]\d{8}|255[67]\d{8}|[67]\d{8})$/.test(digits)
}

function columnLabels(width: number): string[] {
  return Array.from({ length: width }, (_, index) => `Column ${String.fromCharCode(65 + index)}`)
}

export function ImportContacts() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [step, setStep] = useState(0)
  const [file, setFile] = useState<File | null>(null)
  const [rows, setRows] = useState<string[][]>([])
  const [hasHeader, setHasHeader] = useState(true)
  const [nameColumn, setNameColumn] = useState('Name')
  const [phoneColumn, setPhoneColumn] = useState('Phone')
  const [groupColumn, setGroupColumn] = useState('Group')
  const [result, setResult] = useState<ContactImportResult | null>(null)

  const headers = hasHeader
    ? (rows[0] ?? [])
    : columnLabels(Math.max(...rows.map((row) => row.length), 0))
  const bodyRows = hasHeader ? rows.slice(1) : rows
  const columnOptions = [...headers, IGNORE]

  const previewRows = useMemo(() => {
    const nameIndex = headers.indexOf(nameColumn)
    const phoneIndex = headers.indexOf(phoneColumn)
    const groupIndex = groupColumn === IGNORE ? -1 : headers.indexOf(groupColumn)

    return bodyRows.slice(0, 10).map((row, index) => ({
      id: index,
      name: nameIndex >= 0 ? (row[nameIndex] ?? '') : '',
      phone: phoneIndex >= 0 ? (row[phoneIndex] ?? '') : '',
      group: groupIndex >= 0 ? (row[groupIndex] ?? '') : '',
    }))
  }, [bodyRows, groupColumn, headers, nameColumn, phoneColumn])

  const importMutation = useMutation({
    mutationFn: () => {
      if (!file) {
        throw new Error('Upload a CSV file to continue.')
      }

      const phoneIndex = headers.indexOf(phoneColumn)
      if (phoneIndex < 0 || phoneColumn === IGNORE) {
        throw new Error('Choose the phone number column.')
      }

      const nameIndex =
        nameColumn === IGNORE ? null : headers.indexOf(nameColumn)
      const groupIndex =
        groupColumn === IGNORE ? null : headers.indexOf(groupColumn)

      return importContacts(file, {
        name: nameColumn,
        phone: phoneColumn,
        group: groupColumn,
        hasHeader,
        nameIndex: nameIndex !== null && nameIndex >= 0 ? nameIndex : null,
        phoneIndex,
        groupIndex: groupIndex !== null && groupIndex >= 0 ? groupIndex : null,
      })
    },
    onSuccess: async (payload) => {
      setResult(payload)
      await queryClient.invalidateQueries({ queryKey: ['contacts'] })
      await queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })

      if (payload.created > 0) {
        toast.success(
          `Imported ${formatNumber(payload.created)}. Skipped ${formatNumber(payload.skipped)}. Invalid ${formatNumber(payload.invalid)}.`
        )
        return
      }

      const firstError = payload.errors[0]
      toast.error(
        firstError
          ? `No contacts imported. Row ${firstError.row}: ${firstError.reason}`
          : `No contacts imported. Skipped ${formatNumber(payload.skipped)}. Invalid ${formatNumber(payload.invalid)}.`
      )
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  async function onFile(selected: File | null) {
    setFile(selected)
    setResult(null)
    setStep(0)

    if (!selected) {
      setRows([])
      return
    }

    const text = await selected.text()
    const parsed = parseCsvText(text)
    setRows(parsed)
    const headerless =
      (parsed[0] ?? []).some((cell) => looksLikePhone(cell))
    setHasHeader(!headerless)

    if (headerless) {
      const labels = columnLabels(Math.max(parsed[0]?.length ?? 0, 3))
      setPhoneColumn(labels[0] ?? 'Column A')
      setGroupColumn(labels[1] ?? 'Column B')
      setNameColumn((parsed[0]?.length ?? 0) >= 3 ? (labels[2] ?? 'Column C') : IGNORE)
      return
    }

    const nextHeaders = parsed[0] ?? []
    setNameColumn(guessColumn(nextHeaders, ['name', 'full name', 'contact']))
    setPhoneColumn(
      guessColumn(nextHeaders, ['phone', 'phone number', 'mobile', 'number', 'msisdn'])
    )
    const guessedGroup = guessColumn(nextHeaders, ['group', 'category', 'habil'])
    setGroupColumn(
      nextHeaders.some((header) =>
        ['group', 'category'].includes(header.trim().toLowerCase()) ||
        header.trim().toLowerCase().includes('habil')
      )
        ? guessedGroup
        : IGNORE
    )
  }

  function next() {
    if (step === 0 && !file) {
      toast.error('Upload a CSV file to continue.')
      return
    }

    if (step === 0 && (hasHeader ? rows.length < 2 : rows.length < 1)) {
      toast.error(
        hasHeader
          ? 'The file needs a header row and at least one contact.'
          : 'The file needs at least one contact.'
      )
      return
    }

    setStep((value) => Math.min(value + 1, 2))
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Import Contacts'
          description='Upload a CSV. With no header, column A is the number, column B is the group, and column C is the name. Files with a header row are matched automatically.'
        />
        <div className='mb-8 rounded-xl border bg-card p-5'>
          <Stepper steps={steps} current={step} />
        </div>

        {step === 0 ? (
          <section className='rounded-xl border bg-card p-5'>
            <Label htmlFor='contacts-file'>Upload your CSV file</Label>
            <Input
              id='contacts-file'
              className='mt-2'
              type='file'
              accept='.csv,.txt,text/csv'
              onChange={(event) => void onFile(event.target.files?.[0] ?? null)}
            />
            <p className='mt-2 text-sm text-muted-foreground'>
              {file
                ? hasHeader
                  ? `Selected: ${file.name}. Header row detected — columns will be matched from the names in the file.`
                  : `Selected: ${file.name}. No header row. Column A is the number, column B is the group, and column C is the name.`
                : 'Save Excel as CSV. No header: A = number, B = group, C = name. With a header row, columns are detected from the names.'}
            </p>
            <Button className='mt-6' onClick={next}>
              Continue
            </Button>
          </section>
        ) : null}

        {step === 1 ? (
          <section className='rounded-xl border bg-card p-5'>
            <h2 className='font-semibold'>Match columns</h2>
            <p className='mt-1 text-sm text-muted-foreground'>
              {hasHeader
                ? 'These columns were detected from your header row. Change them if a column was matched incorrectly.'
                : 'This file has no header. Column A is always the number, column B is always the group, and column C is the name.'}
            </p>
            <div className='mt-5 grid gap-4 sm:grid-cols-3'>
              <ColumnSelect
                label='Phone Number'
                value={phoneColumn}
                options={columnOptions}
                onChange={setPhoneColumn}
                disabled={!hasHeader}
              />
              <ColumnSelect
                label='Group'
                value={groupColumn}
                options={columnOptions}
                onChange={setGroupColumn}
                disabled={!hasHeader}
              />
              <ColumnSelect
                label='Name'
                value={nameColumn}
                options={columnOptions}
                onChange={setNameColumn}
                disabled={!hasHeader}
              />
            </div>
            <div className='mt-6 flex gap-2'>
              <Button variant='outline' onClick={() => setStep(0)}>
                Back
              </Button>
              <Button onClick={next}>Continue</Button>
            </div>
          </section>
        ) : null}

        {step === 2 ? (
          <section className='space-y-4'>
            <div className='rounded-xl border bg-card p-5'>
              <p className='text-lg font-semibold'>
                {formatNumber(bodyRows.length)} contacts ready to import
              </p>
              <p className='text-sm text-muted-foreground'>
                Review a sample below. If name is ignored, the phone number is
                saved as the name. Duplicate phones are skipped.
              </p>
            </div>
            <TableWrap>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Phone Number</TableHead>
                    <TableHead>Group</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {previewRows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.name}</TableCell>
                      <TableCell>{row.phone}</TableCell>
                      <TableCell>{row.group || '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableWrap>
            {result ? (
              <div className='rounded-xl border bg-card p-5'>
                <p className='font-semibold'>Import finished</p>
                <p className='mt-1 text-sm text-muted-foreground'>
                  Created {formatNumber(result.created)}, skipped{' '}
                  {formatNumber(result.skipped)}, invalid{' '}
                  {formatNumber(result.invalid)}.
                </p>
                {result.errors.length > 0 ? (
                  <ul className='mt-3 list-disc space-y-1 pl-5 text-sm text-muted-foreground'>
                    {result.errors.map((error) => (
                      <li key={`${error.row}-${error.reason}`}>
                        Row {error.row}: {error.reason}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </div>
            ) : null}
            <div className='flex gap-2'>
              <Button variant='outline' onClick={() => setStep(1)}>
                Back
              </Button>
              {result ? (
                <Button onClick={() => navigate({ to: '/contacts' })}>
                  View Contacts
                </Button>
              ) : (
                <Button
                  disabled={importMutation.isPending}
                  onClick={() => importMutation.mutate()}
                >
                  {importMutation.isPending ? 'Importing…' : 'Import Contacts'}
                </Button>
              )}
            </div>
          </section>
        ) : null}
      </Main>
    </>
  )
}

function ColumnSelect({
  label,
  value,
  options,
  onChange,
  disabled = false,
}: {
  label: string
  value: string
  options: string[]
  onChange: (value: string) => void
  disabled?: boolean
}) {
  const unique = Array.from(new Set(options.filter((option) => option !== '')))

  return (
    <div className='space-y-2'>
      <Label>{label}</Label>
      <Select value={value} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {unique.map((option) => (
            <SelectItem key={`${label}-${option}`} value={option}>
              {option === IGNORE ? 'Ignore this column' : option}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
