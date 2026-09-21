export function PageLoader() {
  return (
    <div className='flex min-h-svh items-center justify-center bg-background'>
      <div className='flex flex-col items-center gap-3'>
        <img
          src='/images/soscom-sms-icon.png'
          alt='Soscom SMS'
          className='size-10 object-contain brand-logo-img'
        />
        <p className='text-sm text-muted-foreground'>Loading…</p>
      </div>
    </div>
  )
}
