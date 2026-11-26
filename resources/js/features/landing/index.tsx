import { Link } from '@tanstack/react-router'
import {
  ArrowRight,
  BadgeCheck,
  BatteryFull,
  Code2,
  FileText,
  MessageSquare,
  Send,
  ShieldCheck,
  Signal,
  Wallet,
  Wifi,
} from 'lucide-react'
import { developerApiVisible } from '@/lib/features'
import { PublicSiteFrame } from '@/components/public-site-frame'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'

const features = [
  {
    icon: Send,
    title: 'Bulk SMS',
    description:
      'Send a message to one person or thousands of people from a simple form.',
  },
  {
    icon: Code2,
    title: 'SMS API',
    description:
      'Connect your website or application and send SMS from your own systems.',
  },
  {
    icon: BadgeCheck,
    title: 'Sender IDs',
    description:
      'Use a generic name or request your own business name as the sender.',
  },
  {
    icon: FileText,
    title: 'Delivery Reports',
    description:
      'See which messages were delivered, pending, or failed recipient by recipient.',
  },
]

const faqs = [
  {
    q: 'Do I need technical knowledge to send SMS?',
    a: 'No. You can send SMS from the dashboard with a Sender ID, recipients, and a message.',
  },
  {
    q: 'Can I use my business name as the sender?',
    a: 'Yes. Request a Sender ID and we will review it before it can be used.',
  },
  {
    q: 'How is SMS cost calculated?',
    a: 'Each 160-character part of a message uses 1 SMS per recipient. You always see the estimated cost before sending.',
  },
]

const visibleFeatures = features.filter(
  (feature) => developerApiVisible || feature.title !== 'SMS API'
)

function HeroPhoneMockup() {
  return (
    <figure
      className='relative isolate mx-auto w-[min(100%,18.5rem)] sm:w-[20.5rem]'
      aria-label='Ujumbe wa SMS kutoka YOURBRAND'
    >
      <div
        aria-hidden
        className='absolute inset-x-8 top-16 bottom-10 -z-10 rounded-full bg-primary/25 blur-3xl dark:bg-primary/35'
      />
      <div className='rounded-[2.4rem] bg-zinc-950 p-2.5 shadow-2xl shadow-black/25 ring-1 ring-black/10 dark:ring-white/15'>
        <div className='flex min-h-[29rem] flex-col overflow-hidden rounded-[1.85rem] bg-[#f3f3f5] text-zinc-900 sm:min-h-[32rem]'>
          <div className='relative bg-white px-5 pt-3'>
            <div className='flex items-center justify-between text-[11px] font-semibold tabular-nums'>
              <span>9:41</span>
              <PhoneStatusGlyphs />
            </div>
            <div
              aria-hidden
              className='absolute top-2 left-1/2 h-5 w-16 -translate-x-1/2 rounded-full bg-zinc-950'
            />
          </div>

          <div className='flex items-center gap-3 border-b border-black/5 bg-white px-4 py-3'>
            <div className='flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white'>
              YB
            </div>
            <div className='min-w-0'>
              <p className='truncate text-sm font-semibold tracking-wide'>
                YOURBRAND
              </p>
              <p className='text-[11px] text-zinc-500'>Ujumbe wa SMS</p>
            </div>
          </div>

          <div className='flex-1 px-3.5 pt-4'>
            <div className='max-w-[95%] rounded-2xl rounded-tl-md bg-white px-3.5 py-2.5 text-[13px] leading-relaxed shadow-sm ring-1 ring-black/5 sm:text-sm'>
              Mpendwa mteja, agizo lako limewasili. Tafadhali lichukue leo kabla
              ya saa 12 jioni. Tunakushukuru.
            </div>
            <p className='mt-1.5 pl-1 text-[10px] text-zinc-400'>
              Imewasilishwa · 9:41
            </p>
          </div>

          <div className='px-3 pb-2' aria-hidden>
            <div className='flex items-center rounded-full bg-white px-3.5 py-2.5 text-xs text-zinc-400 ring-1 ring-black/5'>
              Andika ujumbe
            </div>
          </div>
          <div
            aria-hidden
            className='mx-auto mb-2 h-1 w-24 rounded-full bg-zinc-900/80'
          />
        </div>
      </div>
    </figure>
  )
}

function PhoneStatusGlyphs() {
  return (
    <span className='flex items-center gap-1' aria-hidden>
      <Signal className='size-3' strokeWidth={2.5} />
      <Wifi className='size-3' strokeWidth={2.5} />
      <BatteryFull className='size-3.5' strokeWidth={2.5} />
    </span>
  )
}

export function LandingPage() {
  const user = useAuthStore((state) => state.user)

  return (
    <PublicSiteFrame>
      <main>
        <section className='mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:py-24 lg:grid-cols-2'>
          <div className='max-w-3xl'>
            <p className='mb-4 text-sm font-medium text-primary'>
              Bulk SMS for Tanzania
            </p>
            <h1 className='text-4xl font-semibold tracking-tight sm:text-5xl'> Send SMS. Reach Everyone. Grow Faster. </h1> <p className='mt-4 max-w-2xl text-lg text-muted-foreground'> {developerApiVisible ? 'A fast, reliable, and affordable SMS platform built for businesses, organizations, and developers. Send bulk messages to your customers, teams, and communities, or connect your applications through our powerful SMS API.' : 'A fast, reliable, and affordable bulk SMS platform built for businesses and organizations in Tanzania. Send important updates, notifications, reminders, promotions, and announcements to thousands of people with ease.'} </p>
            <div className='mt-8 flex flex-col gap-3 sm:flex-row'>
              {user ? (
                <Button size='lg' asChild>
                  <Link to='/dashboard'>
                    Go to dashboard
                    <ArrowRight />
                  </Link>
                </Button>
              ) : (
                <Button size='lg' asChild>
                  <Link to='/sign-up'>
                    Start Sending SMS
                    <ArrowRight />
                  </Link>
                </Button>
              )}
              {developerApiVisible ? (
                <Button size='lg' variant='outline' asChild>
                  <a href='#developers'>Explore API</a>
                </Button>
              ) : null}
            </div>
          </div>
          <HeroPhoneMockup />
        </section>

        <section
          id='features'
          className='bg-muted/30 py-16 sm:py-20'
        >
          <div className='mx-auto max-w-6xl px-4'>
            <h2 className='text-2xl font-semibold tracking-tight'>
              Everything you need to send SMS
            </h2>
            <p className='mt-2 max-w-2xl text-muted-foreground'>
              Clear tools for sending messages, managing contacts, buying SMS,
              and tracking delivery.
            </p>
            <div
              className={`mt-10 grid gap-4 sm:grid-cols-2 ${
                visibleFeatures.length > 3 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'
              }`}
            >
              {visibleFeatures.map((feature) => (
                <div
                  key={feature.title}
                  className='rounded-xl border bg-card p-5'
                >
                  <feature.icon className='mb-4 size-5 text-primary' />
                  <h3 className='font-medium'>{feature.title}</h3>
                  <p className='mt-2 text-sm text-muted-foreground'>
                    {feature.description}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {developerApiVisible ? (
        <section
          id='developers'
          className='border-y bg-muted/30 py-16 sm:py-20'
        >
          <div className='mx-auto grid max-w-6xl items-center gap-10 px-4 lg:grid-cols-2'>
            <div>
              <h2 className='text-2xl font-semibold tracking-tight'>
                Developer Integration
              </h2>
              <p className='mt-2 text-muted-foreground'>
                Connect your application to Soscom SMS with an API key and a
                Send SMS endpoint.
              </p>
              <ul className='mt-6 space-y-3 text-sm'>
                <li className='flex items-center gap-2'>
                  <ShieldCheck className='size-4 text-primary' />
                  API keys you can create and revoke
                </li>
                <li className='flex items-center gap-2'>
                  <MessageSquare className='size-4 text-primary' />
                  Send one SMS or bulk SMS
                </li>
                <li className='flex items-center gap-2'>
                  <Wallet className='size-4 text-primary' />
                  Clear credit usage on every request
                </li>
              </ul>
            
            </div>
            <pre className='overflow-x-auto rounded-xl border bg-card p-5 text-sm'>
              {`curl https://sms.soscom.co.tz/api/v1/sms/send \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Content-Type: application/json" \\
  -d '{
    "sender_id": "MWALIKO",
    "to": "+255712XXX221",
    "message": "Fee reminder for this week."
  }'`}
            </pre>
          </div>
        </section>
        ) : null}

        <section id='faq' className='mx-auto max-w-6xl px-4 py-16 sm:py-20'>
          <h2 className='text-2xl font-semibold tracking-tight'>FAQ</h2>
          <div className='mt-8 grid gap-6 md:grid-cols-3'>
            {faqs.map((item) => (
              <div key={item.q} className='rounded-xl border bg-card p-5'>
                <h3 className='font-medium'>{item.q}</h3>
                <p className='mt-2 text-sm text-muted-foreground'>{item.a}</p>
              </div>
            ))}
          </div>
        </section>
      </main>
    </PublicSiteFrame>
  )
}
