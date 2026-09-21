import { PublicSiteFrame } from '@/components/public-site-frame'

export function LegalLayout({
  title,
  children,
}: {
  title: string
  children: React.ReactNode
}) {
  return (
    <PublicSiteFrame>
      <main className='mx-auto max-w-6xl px-4 py-12 sm:py-16'>
        <h1 className='text-3xl font-semibold tracking-tight sm:text-4xl'>
          {title}
        </h1>
        <p className='mt-2 text-sm text-muted-foreground'>
          SOSCOM Technologies Company Limited · sms.soscom.co.tz · 20 September
          2026
        </p>
        <div className='mt-10 space-y-8 text-sm leading-6'>{children}</div>
      </main>
    </PublicSiteFrame>
  )
}

export function LegalSection({
  title,
  children,
}: {
  title: string
  children: React.ReactNode
}) {
  return (
    <section>
      <h2 className='mb-2 text-base font-semibold'>{title}</h2>
      <div className='space-y-3 text-muted-foreground'>{children}</div>
    </section>
  )
}
