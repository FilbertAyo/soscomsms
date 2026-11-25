import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  contactGroupsQueryKey,
  fetchContactGroups,
  importContacts,
} from '@/lib/contacts'
import { formatNumber } from '@/lib/format'
import { parseSpreadsheetFile, SPREADSHEET_ACCEPT } from '@/lib/spreadsheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { QueryLoadError } from '@/components/soscom/query-load-error'
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

type GroupChoice =
  | { contactGroupId: number; groupName: null }
  | { contactGroupId: null; groupName: string }

function guessColumn(
  headers: string[],
  candidates: string[],
  fallback: string,
): string {
  const lowered = headers.map((header) => header.trim().toLowerCase())
  for (const candidate of candidates) {
    const index = lowered.indexOf(candidate)
    if (index >= 0) {
      return headers[index] ?? fallback
    }
  }

  return fallback
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
  const [groupMode, setGroupMode] = useState<'existing' | 'new'>('existing')
  const [groupId, setGroupId] = useState('')
  const [groupName, setGroupName] = useState('')
  const [result, setResult] = useState<ContactImportResult | null>(null)
  const groupsQuery = useQuery({
    queryKey: contactGroupsQueryKey,
    queryFn: fetchContactGroups,
  })
  const groups = groupsQuery.data ?? []

  const headers = hasHeader
    ? (rows[0] ?? [])
    : columnLabels(Math.max(...rows.map((row) => row.length), 0))
  const bodyRows = hasHeader ? rows.slice(1) : rows
  const columnOptions = [...headers, IGNORE]

  const previewRows = useMemo(() => {
    const nameIndex = headers.indexOf(nameColumn)
    const phoneIndex = headers.indexOf(phoneColumn)

    return bodyRows.slice(0, 10).map((row, index) => ({
      id: index,
      name: nameIndex >= 0 ? (row[nameIndex] ?? '') : '',
      phone: phoneIndex >= 0 ? (row[phoneIndex] ?? '') : '',
    }))
  }, [bodyRows, headers, nameColumn, phoneColumn])

  const importMutation = useMutation({
    mutationFn: (group: GroupChoice) => {
      if (!file) {
        throw new Error('Upload a CSV or Excel file to continue.')
      }

      const phoneIndex = headers.indexOf(phoneColumn)
      if (phoneIndex < 0 || phoneColumn === IGNORE) {
        throw new Error('Choose the phone number column.')
      }

      const nameIndex =
        nameColumn === IGNORE ? null : headers.indexOf(nameColumn)

      return importContacts(file, {
        name: nameColumn,
        phone: phoneColumn,
        hasHeader,
        nameIndex: nameIndex !== null && nameIndex >= 0 ? nameIndex : null,
        phoneIndex,
        contactGroupId: group.contactGroupId,
        groupName: group.groupName,
      })
    },
    onSuccess: async (payload) => {
      setResult(payload)
      void queryClient.invalidateQueries({ queryKey: ['contacts'] })
      void queryClient.invalidateQueries({ queryKey: contactGroupsQueryKey })

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

  async function onFile(selected: File | null, input?: HTMLInputElement) {
    setFile(selected)
    setResult(null)
    setStep(0)

    if (!selected) {
      setRows([])
      return
    }

    let parsed: string[][]
    try {
      parsed = await parseSpreadsheetFile(selected)
    } catch {
      setFile(null)
      setRows([])
      if (input) {
        input.value = ''
      }
      toast.error('The file could not be read. Use a CSV or Excel file.')
      return
    }

    setRows(parsed)
    const headerless = (parsed[0] ?? []).some((cell) => looksLikePhone(cell))
    setHasHeader(!headerless)

    if (headerless) {
      const labels = columnLabels(Math.max(parsed[0]?.length ?? 0, 1))
      setPhoneColumn(labels[0] ?? 'Column A')
      setNameColumn((parsed[0]?.length ?? 0) >= 2 ? (labels[1] ?? 'Column B') : IGNORE)
      return
    }

    const nextHeaders = parsed[0] ?? []
    setNameColumn(guessColumn(nextHeaders, ['name', 'full name', 'contact'], IGNORE))
    setPhoneColumn(
      guessColumn(
        nextHeaders,
        ['phone', 'phone number', 'mobile', 'number', 'msisdn'],
        nextHeaders[0] ?? IGNORE,
      ),
    )
  }

  function next() {
    if (step === 0 && !file) {
      toast.error('Upload a CSV or Excel file to continue.')
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

    if (step === 1 && (phoneColumn === IGNORE || headers.indexOf(phoneColumn) < 0)) {
      toast.error('Choose the phone number column.')
      return
    }

    setStep((value) => Math.min(value + 1, 2))
  }

  function chosenGroup(): GroupChoice | null {
    const creating = groups.length === 0 || groupMode === 'new'
    if (creating) {
      const name = groupName.trim()
      if (name === '') {
        toast.error('Name the group for these contacts.')
        return null
      }
      if (name.length > 80) {
        toast.error('Use a group name of 80 characters or less.')
        return null
      }

      return { contactGroupId: null, groupName: name }
    }

    const id = Number(groupId)
    if (!Number.isInteger(id) || id < 1) {
      toast.error('Select a group for these contacts.')
      return null
    }

    return { contactGroupId: id, groupName: null }
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Import Contacts'
          description='Upload a CSV or Excel file with phone numbers and names. On the last step, add those contacts to a group you already have, or name a new one.'
        />
        <div className='mb-8 rounded-xl border bg-card p-5'>
          <Stepper steps={steps} current={step} />
        </div>

        {step === 0 ? (
          <section className='rounded-xl border bg-card p-5'>
            <Label htmlFor='contacts-file'>Upload your CSV or Excel file</Label>
            <Input
              id='contacts-file'
              className='mt-2'
              type='file'
              accept={SPREADSHEET_ACCEPT}
              onChange={(event) => {
                const input = event.currentTarget
                void onFile(input.files?.[0] ?? null, input)
              }}
            />
            <p className='mt-2 text-sm text-muted-foreground'>
              {file
                ? hasHeader
                  ? `Selected: ${file.name}. Header row detected — match the phone and name columns.`
                  : `Selected: ${file.name}. No header row. Column A is the number and column B is the name.`
                : 'CSV or Excel. No header: column A is the number and column B is the name. With a header row, columns are matched from the names. You choose the group on the last step.'}
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
                : 'This file has no header. Column A is the number and column B is the name.'}
            </p>
            <div className='mt-5 grid gap-4 sm:grid-cols-2'>
              <ColumnSelect
                label='Phone Number'
                value={phoneColumn}
                options={columnOptions}
                onChange={setPhoneColumn}
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
              <div className='mt-5'>
                <h2 className='font-semibold'>Group</h2>
                <p className='mt-1 text-sm text-muted-foreground'>
                  The file does not include a group. Add every contact to one group.
                </p>
                {groupsQuery.isPending ? (
                  <p className='mt-4 text-sm text-muted-foreground'>Loading groups…</p>
                ) : groupsQuery.isError ? (
                  <QueryLoadError
                    className='mt-4'
                    message="We couldn't load contact groups."
                    onRetry={() => void groupsQuery.refetch()}
                  />
                ) : groups.length === 0 ? (
                  <div className='mt-4 max-w-md space-y-2'>
                    <Label htmlFor='import-group-name'>New group name</Label>
                    <Input
                      id='import-group-name'
                      value={groupName}
                      maxLength={80}
                      onChange={(event) => setGroupName(event.target.value)}
                    />
                  </div>
                ) : (
                  <div className='mt-4 max-w-md space-y-4'>
                    <RadioGroup
                      value={groupMode}
                      onValueChange={(value) => {
                        if (value === 'existing' || value === 'new') {
                          setGroupMode(value)
                        }
                      }}
                    >
                      <div className='flex items-center gap-2'>
                        <RadioGroupItem value='existing' id='group-existing' />
                        <Label htmlFor='group-existing'>Add to an existing group</Label>
                      </div>
                      <div className='flex items-center gap-2'>
                        <RadioGroupItem value='new' id='group-new' />
                        <Label htmlFor='group-new'>Name a new group</Label>
                      </div>
                    </RadioGroup>
                    {groupMode === 'existing' ? (
                      <Select value={groupId || undefined} onValueChange={setGroupId}>
                        <SelectTrigger>
                          <SelectValue placeholder='Select a group' />
                        </SelectTrigger>
                        <SelectContent>
                          {groups.map((group) => (
                            <SelectItem key={group.id} value={String(group.id)}>
                              {group.name} ({formatNumber(group.contactsCount)})
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    ) : (
                      <div className='space-y-2'>
                        <Label htmlFor='import-group-name'>New group name</Label>
                        <Input
                          id='import-group-name'
                          value={groupName}
                          maxLength={80}
                          onChange={(event) => setGroupName(event.target.value)}
                        />
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
            <TableWrap>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Phone Number</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {previewRows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.name}</TableCell>
                      <TableCell>{row.phone}</TableCell>
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
                  disabled={
                    importMutation.isPending ||
                    groupsQuery.isPending ||
                    groupsQuery.isError
                  }
                  onClick={() => {
                    const group = chosenGroup()
                    if (!group) {
                      return
                    }
                    importMutation.mutate(group)
                  }}
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
