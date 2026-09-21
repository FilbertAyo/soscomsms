import { cn } from '@/lib/utils'

type BrandLogoProps = {
  className?: string
  imgClassName?: string
  showWordmark?: boolean
}

export function BrandLogo({
  className,
  imgClassName,
  showWordmark = false,
}: BrandLogoProps) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <img
        src='/images/soscom-sms-icon.png'
        alt=''
        className={cn(
          'size-8 shrink-0 object-contain brand-logo-img',
          imgClassName
        )}
      />
      {showWordmark ? (
        <span className='text-xl font-semibold tracking-tight'>
          <span className='text-foreground'>soscom</span>
          <span className='text-primary'>sms</span>
        </span>
      ) : null}
      <span className='sr-only'>Soscom SMS</span>
    </span>
  )
}
