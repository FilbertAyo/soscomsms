import { cn } from '@/lib/utils'

export function TableWrap({
  className,
  children,
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        'overflow-x-auto rounded-xl border bg-card',
        className
      )}
    >
      {children}
    </div>
  )
}
