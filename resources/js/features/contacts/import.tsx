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

export function ImportContacts() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [step, setStep] = useState(0)
  const [file, setFile] = useState<File | null>(null)
  const [rows, setRows] = useState<string[][]>([])
  const [nameColumn, setNameColumn] = useState('Name')
  const [phoneColumn, setPhoneColumn] = useState('Phone')
  const [groupColumn, setGroupColumn] = useState('Group')
  const [result, setResult] = useState<ContactImportResult | null>(null)

  const headers = rows[0] ?? []
  const bodyRows = rows.slice(1)
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

      return importContacts(file, {
        name: nameColumn,
        phone: phoneColumn,
        group: groupColumn,
      })
    },
    onSuccess: async (payload) => {
      setResult(payload)
      await queryClient.invalidateQueries({ queryKey: ['contacts'] })
      await queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })

      if (payload.status === 'queued') {
        toast.success(
          payload.message ?? 'Your contacts are importing in the background.'
        )
        navigate({ to: '/contacts' })
        return
      }

      if (payload.created > 0) {
        toast.success(
          `Imported ${formatNumber(payload.created)}. Skipped ${formatNumber(payload.skipped)}. Invalid ${formatNumber(payload.invalid)}.`
        )
        return
      }

      toast.error(
        `No contacts imported. Skipped ${formatNumber(payload.skipped)}. Invalid ${formatNumber(payload.invalid)}.`
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
    const nextHeaders = parsed[0] ?? []
    setNameColumn(guessColumn(nextHeaders, ['name', 'full name', 'contact']))
    setPhoneColumn(
      guessColumn(nextHeaders, ['phone', 'phone number', 'mobile', 'msisdn'])
    )
    const guessedGroup = guessColumn(nextHeaders, ['group', 'category'])
    setGroupColumn(
      ['group', 'category'].includes(guessedGroup.trim().toLowerCase())
        ? guessedGroup
        : IGNORE
    )
  }

  function next() {
    if (step === 0 && !file) {
      toast.error('Upload a CSV file to continue.')
      return
    }

    if (step === 0 && rows.length < 2) {
      toast.error('The file needs a header row and at least one contact.')
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
          description='Upload a CSV file, match the columns, then review before importing. Duplicate Tanzanian numbers are skipped.'
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
                ? `Selected: ${file.name}`
                : 'Use columns such as Name, Phone, and Group. Excel files should be saved as CSV first.'}
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
              Tell us which column in your file is name, phone number, and group.
            </p>
            <div className='mt-5 grid gap-4 sm:grid-cols-3'>
              <ColumnSelect
                label='Name'
                value={nameColumn}
                options={columnOptions}
                onChange={setNameColumn}
              />
              <ColumnSelect
                label='Phone Number'
                value={phoneColumn}
                options={columnOptions}
                onChange={setPhoneColumn}
              />
              <ColumnSelect
                label='Group'
                value={groupColumn}
                options={columnOptions}
                onChange={setGroupColumn}
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
                Review a sample below. Duplicate phones for this account are
                skipped. Invalid numbers are reported, not saved.
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
}: {
  label: string
  value: string
  options: string[]
  onChange: (value: string) => void
}) {
  const unique = Array.from(new Set(options.filter((option) => option !== '')))

  return (
    <div className='space-y-2'>
      <Label>{label}</Label>
      <Select value={value} onValueChange={onChange}>
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
