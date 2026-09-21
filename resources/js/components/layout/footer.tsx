import { cn } from '@/lib/utils'

type FooterProps = React.ComponentProps<'footer'>

export function Footer({ className, ...props }: FooterProps) {
  return (
    <footer
      className={cn(
        'mt-auto shrink-0 border-t px-4 py-4 text-center text-sm text-muted-foreground',
        className
      )}
      {...props}
    >
      <span>
        &copy; {new Date().getFullYear()} SOSCOM Technologies. All rights
        reserved.
      </span>
    </footer>
  )
}
