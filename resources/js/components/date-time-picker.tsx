import { useMemo, useState } from 'react'
import { format, isBefore, startOfDay } from 'date-fns'
import { Calendar as CalendarIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

type DateTimePickerProps = {
  id?: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
}

function parseDateTimeLocal(value: string): Date | undefined {
  if (!value) return undefined
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value)
  if (!match) return undefined
  const [, year, month, day, hour, minute] = match
  const date = new Date(
    Number(year),
    Number(month) - 1,
    Number(day),
    Number(hour),
    Number(minute)
  )
  return Number.isNaN(date.getTime()) ? undefined : date
}

function toDateTimeLocal(date: Date, time: string): string {
  const [hour = '00', minute = '00'] = time.split(':')
  const next = new Date(date)
  next.setHours(Number(hour), Number(minute), 0, 0)
  return format(next, "yyyy-MM-dd'T'HH:mm")
}

export function DateTimePicker({
  id,
  value,
  onChange,
  placeholder = 'Pick date and time',
  className,
}: DateTimePickerProps) {
  const [open, setOpen] = useState(false)
  const selected = useMemo(() => parseDateTimeLocal(value), [value])
  const timeValue = selected ? format(selected, 'HH:mm') : '09:00'
  const today = startOfDay(new Date())

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          id={id}
          type='button'
          variant='outline'
          data-empty={!selected}
          className={cn(
            'w-full justify-start text-start font-normal data-[empty=true]:text-muted-foreground',
            className
          )}
        >
          {selected ? (
            format(selected, 'MMM d, yyyy · HH:mm')
          ) : (
            <span>{placeholder}</span>
          )}
          <CalendarIcon className='ms-auto size-4 opacity-50' />
        </Button>
      </PopoverTrigger>
      <PopoverContent align='start' className='w-auto p-0'>
        <Calendar
          mode='single'
          captionLayout='dropdown'
          selected={selected}
          onSelect={(date) => {
            if (!date) {
              onChange('')
              return
            }
            onChange(toDateTimeLocal(date, timeValue))
          }}
          disabled={(date) => isBefore(startOfDay(date), today)}
          defaultMonth={selected ?? today}
        />
        <div className='space-y-2 border-t p-3'>
          <Label htmlFor={id ? `${id}-time` : undefined}>Time</Label>
          <Input
            id={id ? `${id}-time` : undefined}
            type='time'
            value={timeValue}
            onChange={(event) => {
              const nextTime = event.target.value || '09:00'
              const base = selected ?? today
              onChange(toDateTimeLocal(base, nextTime))
            }}
          />
        </div>
      </PopoverContent>
    </Popover>
  )
}
