import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

type TimelineStep = {
  id: string
  label: string
}

type RequestTimelineProps = {
  steps: TimelineStep[]
  currentIndex: number
}

export function RequestTimeline({
  steps,
  currentIndex,
}: RequestTimelineProps) {
  return (
    <ol className='space-y-4'>
      {steps.map((step, index) => {
        const done = index <= currentIndex
        const current = index === currentIndex
        return (
          <li key={step.id} className='flex gap-3'>
            <div className='flex flex-col items-center'>
              <div
                className={cn(
                  'flex size-7 items-center justify-center rounded-full border',
                  done
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'text-muted-foreground'
                )}
              >
                {done ? <Check className='size-3.5' /> : <span className='size-2 rounded-full bg-muted-foreground/40' />}
              </div>
              {index < steps.length - 1 ? (
                <div
                  className={cn(
                    'mt-1 w-px flex-1 min-h-6',
                    index < currentIndex ? 'bg-primary' : 'bg-border'
                  )}
                />
              ) : null}
            </div>
            <div className='pt-0.5'>
              <p className={cn('text-sm', current && 'font-medium')}>
                {step.label}
              </p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
