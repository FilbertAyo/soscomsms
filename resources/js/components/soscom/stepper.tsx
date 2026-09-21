import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

type Step = {
  id: string
  label: string
}

type StepperProps = {
  steps: Step[]
  current: number
}

export function Stepper({ steps, current }: StepperProps) {
  return (
    <ol className='flex flex-col gap-4 sm:flex-row sm:items-center'>
      {steps.map((step, index) => {
        const complete = index < current
        const active = index === current
        return (
          <li key={step.id} className='flex flex-1 items-center gap-3'>
            <div
              className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-medium',
                complete &&
                  'border-primary bg-primary text-primary-foreground',
                active && 'border-primary text-primary',
                !complete && !active && 'text-muted-foreground'
              )}
            >
              {complete ? <Check className='size-4' /> : index + 1}
            </div>
            <span
              className={cn(
                'text-sm',
                active ? 'font-medium' : 'text-muted-foreground'
              )}
            >
              {step.label}
            </span>
            {index < steps.length - 1 ? (
              <div className='hidden h-px flex-1 bg-border sm:block' />
            ) : null}
          </li>
        )
      })}
    </ol>
  )
}
