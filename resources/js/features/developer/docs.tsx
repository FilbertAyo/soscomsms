import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@tanstack/react-router'
import { Check, Copy } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'
import { cn } from '@/lib/utils'

const sections = [
  { id: 'introduction', title: 'Introduction' },
  { id: 'authentication', title: 'Authentication' },
  { id: 'send-sms', title: 'Send SMS' },
  { id: 'bulk-sms', title: 'Bulk SMS' },
  { id: 'balance', title: 'Balance' },
  { id: 'sender-ids', title: 'Sender IDs' },
  { id: 'message-status', title: 'Message status' },
  { id: 'delivery-reports', title: 'Delivery reports' },
  { id: 'webhooks', title: 'Webhooks' },
  { id: 'error-codes', title: 'Error codes' },
] as const

type SectionId = (typeof sections)[number]['id']

function apiBase(): string {
  if (typeof window === 'undefined') {
    return 'https://sms.soscom.co.tz'
  }

  return window.location.origin
}

function examples(base: string) {
  return {
    curl: {
      send: `curl ${base}/api/v1/sms/send \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Idempotency-Key: fee-reminder-001" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"sender_id":"MWALIKO","to":"+255712445221","message":"Fee reminder"}'`,
      bulk: `curl ${base}/api/v1/sms/bulk \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Idempotency-Key: fee-reminder-bulk-001" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -d '{"sender_id":"MWALIKO","to":["+255712445221","+255754221890"],"message":"Fee reminder"}'`,
      balance: `curl ${base}/api/v1/balance \\
  -H "Authorization: Bearer sk_live_••••" \\
  -H "Accept: application/json"`,
    },
    php: {
      send: `$response = Http::withToken('sk_live_••••')
  ->acceptJson()
  ->withHeader('Idempotency-Key', 'fee-reminder-001')
  ->post('${base}/api/v1/sms/send', [
    'sender_id' => 'MWALIKO',
    'to' => '+255712445221',
    'message' => 'Fee reminder',
  ]);`,
      bulk: `$response = Http::withToken('sk_live_••••')
  ->acceptJson()
  ->withHeader('Idempotency-Key', 'fee-reminder-bulk-001')
  ->post('${base}/api/v1/sms/bulk', [
    'sender_id' => 'MWALIKO',
    'to' => ['+255712445221', '+255754221890'],
    'message' => 'Fee reminder',
  ]);`,
      balance: `$response = Http::withToken('sk_live_••••')
  ->acceptJson()
  ->get('${base}/api/v1/balance');`,
    },
    javascript: {
      send: `await fetch('${base}/api/v1/sms/send', {
  method: 'POST',
  headers: {
    Authorization: 'Bearer sk_live_••••',
    'Idempotency-Key': 'fee-reminder-001',
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    sender_id: 'MWALIKO',
    to: '+255712445221',
    message: 'Fee reminder',
  }),
})`,
      bulk: `await fetch('${base}/api/v1/sms/bulk', {
  method: 'POST',
  headers: {
    Authorization: 'Bearer sk_live_••••',
    'Idempotency-Key': 'fee-reminder-bulk-001',
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    sender_id: 'MWALIKO',
    to: ['+255712445221', '+255754221890'],
    message: 'Fee reminder',
  }),
})`,
      balance: `await fetch('${base}/api/v1/balance', {
  headers: {
    Authorization: 'Bearer sk_live_••••',
    Accept: 'application/json',
  },
})`,
    },
    python: {
      send: `requests.post(
  '${base}/api/v1/sms/send',
  headers={
    'Authorization': 'Bearer sk_live_••••',
    'Idempotency-Key': 'fee-reminder-001',
    'Accept': 'application/json',
  },
  json={
    'sender_id': 'MWALIKO',
    'to': '+255712445221',
    'message': 'Fee reminder',
  },
)`,
      bulk: `requests.post(
  '${base}/api/v1/sms/bulk',
  headers={
    'Authorization': 'Bearer sk_live_••••',
    'Idempotency-Key': 'fee-reminder-bulk-001',
    'Accept': 'application/json',
  },
  json={
    'sender_id': 'MWALIKO',
    'to': ['+255712445221', '+255754221890'],
    'message': 'Fee reminder',
  },
)`,
      balance: `requests.get(
  '${base}/api/v1/balance',
  headers={'Authorization': 'Bearer sk_live_••••', 'Accept': 'application/json'},
)`,
    },
  } as const
}

function scrollToSection(id: SectionId) {
  const node = document.getElementById(id)
  if (node === null) {
    return
  }

  window.history.replaceState(null, '', `#${id}`)
  node.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function ApiDocsPage() {
  const [active, setActive] = useState<SectionId>('introduction')
  const base = apiBase()

  useEffect(() => {
    const hash = window.location.hash.replace('#', '') as SectionId
    if (sections.some((section) => section.id === hash)) {
      setActive(hash)
      requestAnimationFrame(() => {
        document.getElementById(hash)?.scrollIntoView({ block: 'start' })
      })
    }

    const observed = sections
      .map((section) => document.getElementById(section.id))
      .filter((node): node is HTMLElement => node !== null)

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)

        const next = visible[0]?.target.id as SectionId | undefined
        if (next) {
          setActive(next)
        }
      },
      { rootMargin: '-20% 0px -65% 0px', threshold: [0, 0.25, 1] }
    )

    observed.forEach((node) => observer.observe(node))

    return () => observer.disconnect()
  }, [])

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='API Documentation'
          description='Send Tanzanian SMS from your own application. Developers talk only to Soscom SMS — never to the upstream gateway.'
        />
        <div className='grid items-start gap-8 lg:grid-cols-[13.5rem_minmax(0,1fr)]'>
          <nav className='lg:sticky lg:top-6 lg:self-start'>
            <p className='mb-2 px-3 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase'>
              On this page
            </p>
            <ul className='flex flex-col gap-0.5'>
              {sections.map((section) => (
                <li key={section.id}>
                  <a
                    href={`#${section.id}`}
                    onClick={(event) => {
                      event.preventDefault()
                      setActive(section.id)
                      scrollToSection(section.id)
                    }}
                    className={cn(
                      'block rounded-md px-3 py-2 text-sm leading-5 hover:bg-muted hover:text-foreground',
                      active === section.id
                        ? 'bg-muted font-medium text-foreground'
                        : 'text-muted-foreground'
                    )}
                  >
                    {section.title}
                  </a>
                </li>
              ))}
            </ul>
          </nav>
          <div className='min-w-0 space-y-10'>
            <IntroSection base={base} />
            <AuthSection />
            <SendSection base={base} />
            <BulkSection base={base} />
            <BalanceSection base={base} />
            <SenderIdsSection base={base} />
            <StatusSection base={base} />
            <ReportsSection base={base} />
            <WebhooksSection />
            <ErrorsSection />
          </div>
        </div>
      </Main>
    </>
  )
}

function IntroSection({ base }: { base: string }) {
  return (
    <DocSection id='introduction' title='Introduction'>
      <p>
        Soscom SMS is a JSON API for Tanzanian mobile numbers only (
        <code>+255</code> / <code>07…</code>). Create a key on{' '}
        <Link to='/developer' className='font-medium text-primary hover:underline'>
          API keys
        </Link>
        , then call these endpoints. Your customers and servers do not need an
        IP whitelist. Soscom sends from its own server after the upstream
        provider allows that address.
      </p>
      <Callout>
        Production base URL is <code>https://sms.soscom.co.tz/api/v1</code>.
        This environment uses <code>{base}/api/v1</code>.
      </Callout>
      <ul className='space-y-2 text-sm'>
        <Endpoint method='POST' path='/api/v1/sms/send' />
        <Endpoint method='POST' path='/api/v1/sms/bulk' />
        <Endpoint method='GET' path='/api/v1/balance' />
        <Endpoint method='GET' path='/api/v1/sender-ids' />
        <Endpoint method='GET' path='/api/v1/sms/{id}' />
        <Endpoint method='GET' path='/api/v1/messages/{id}' />
        <Endpoint method='GET' path='/api/v1/reports' />
      </ul>
      <p>
        Credits are deducted when SOSCOM accepts the send into its queue — the
        same as the dashboard. A gateway reject is refunded. A timeout is left
        as unknown (no retry, no refund). A later delivery failure is not
        refunded.
      </p>
    </DocSection>
  )
}

function AuthSection() {
  return (
    <DocSection id='authentication' title='Authentication'>
      <p>
        Send the full API key on every request. It is shown once when you
        create it. Use either header:
      </p>
      <CodeBlock>{`Authorization: Bearer sk_live_••••••••••••••••
X-Api-Key: sk_live_••••••••••••••••`}</CodeBlock>
      <ul className='list-disc space-y-1 pl-5'>
        <li>60 requests per minute per API key.</li>
        <li>
          Optional <code>Idempotency-Key</code> on send and bulk. Same key and
          body returns the first response. Same key and a different body
          returns 409.
        </li>
        <li>
          Every response includes <code>X-Request-Id</code>. You may send your
          own; otherwise Soscom creates one.
        </li>
        <li>Revoked keys and missing keys return 401.</li>
        <li>Suspended or incomplete accounts return 403.</li>
      </ul>
    </DocSection>
  )
}

function SendSection({ base }: { base: string }) {
  return (
    <DocSection id='send-sms' title='Send SMS'>
      <Endpoint method='POST' path='/api/v1/sms/send' />
      <p>
        <code>to</code> may be one Tanzanian number or an array.{' '}
        <code>sender_id</code> must be an active Sender ID on this account.
        Optional <code>name</code> labels the campaign. GSM messages use 160
        characters (153 when concatenated). Unicode uses 70 (63 concatenated).
      </p>
      <CodeExamples example='send' base={base} />
      <p className='pt-2 font-medium text-foreground'>201 response</p>
      <CodeBlock>{`{
  "id": 21,
  "name": "Fee reminder",
  "status": "queued",
  "sender_id": "MWALIKO",
  "message": "Fee reminder",
  "encoding": "gsm",
  "character_count": 13,
  "segments": 1,
  "recipients": 1,
  "sent": 0,
  "delivered": 0,
  "failed": 0,
  "sms": 1,
  "sms_balance": 19,
  "messages": [
    {
      "id": 41,
      "campaign_id": 21,
      "to": "+255712445221",
      "status": "queued",
      "segments": 1,
      "provider_message_id": null,
      "delivered_at": null
    }
  ]
}`}</CodeBlock>
    </DocSection>
  )
}

function BulkSection({ base }: { base: string }) {
  return (
    <DocSection id='bulk-sms' title='Bulk SMS'>
      <Endpoint method='POST' path='/api/v1/sms/bulk' />
      <p>
        <code>to</code> must be an array of Tanzanian numbers (up to 5,000).
        Cost, Sender ID rules, and the credit ledger match a dashboard
        campaign.
      </p>
      <CodeExamples example='bulk' base={base} />
    </DocSection>
  )
}

function BalanceSection({ base }: { base: string }) {
  return (
    <DocSection id='balance' title='Balance'>
      <Endpoint method='GET' path='/api/v1/balance' />
      <p>Returns remaining SMS credits for the key’s account.</p>
      <CodeExamples example='balance' base={base} />
      <CodeBlock>{`{ "sms_balance": 10104 }`}</CodeBlock>
    </DocSection>
  )
}

function SenderIdsSection({ base }: { base: string }) {
  return (
    <DocSection id='sender-ids' title='Sender IDs'>
      <Endpoint method='GET' path='/api/v1/sender-ids' />
      <p>
        Lists Sender IDs you can send with, including shared SOSCOM names.
        Pending requests cannot be used. Another account’s Sender ID returns
        403.
      </p>
      <CodeBlock>{`GET ${base}/api/v1/sender-ids

{
  "sender_ids": [
    { "id": 1, "name": "HARUSI", "status": "active", "source": "generic" }
  ]
}`}</CodeBlock>
    </DocSection>
  )
}

function StatusSection({ base }: { base: string }) {
  return (
    <DocSection id='message-status' title='Message status'>
      <Endpoint method='GET' path='/api/v1/sms/{id}' />
      <Endpoint method='GET' path='/api/v1/messages/{id}' />
      <p>
        Campaign <code>id</code> comes from the send response. Recipient{' '}
        <code>id</code> is each item in <code>messages</code>. Status values:{' '}
        <code>pending</code>, <code>queued</code>, <code>sent</code>,{' '}
        <code>delivered</code>, <code>failed</code>, <code>rejected</code>,{' '}
        <code>unknown</code>.
      </p>
      <CodeBlock>{`GET ${base}/api/v1/sms/21
GET ${base}/api/v1/messages/41`}</CodeBlock>
    </DocSection>
  )
}

function ReportsSection({ base }: { base: string }) {
  return (
    <DocSection id='delivery-reports' title='Delivery reports'>
      <Endpoint method='GET' path='/api/v1/reports' />
      <p>
        Lists deliveries for this account. Filter with{' '}
        <code>campaign_id</code>, <code>id</code> (recipient id), <code>to</code>
        , or <code>q</code>. Paginate with <code>per_page</code> (max 200).
      </p>
      <CodeBlock>{`GET ${base}/api/v1/reports?to=+255712445221

{
  "reports": [
    {
      "id": 41,
      "to": "+255712445221",
      "campaign_id": 21,
      "campaign_name": "Fee reminder",
      "sender_id": "MWALIKO",
      "message": "Fee reminder",
      "status": "delivered",
      "provider_message_id": "FAKE-41",
      "sent_at": "2026-09-20T15:01:00+03:00",
      "delivered_at": "2026-09-20T15:01:00+03:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}`}</CodeBlock>
    </DocSection>
  )
}

function WebhooksSection() {
  return (
    <DocSection id='webhooks' title='Webhooks'>
      <p>
        Create a webhook on the{' '}
        <Link
          to='/developer/webhooks'
          className='font-medium text-primary hover:underline'
        >
          Webhooks
        </Link>{' '}
        page. Soscom POSTs JSON for <code>delivery.updated</code> and{' '}
        <code>campaign.completed</code>. Production URLs must be HTTPS.
      </p>
      <p>
        Headers: <code>X-Soscom-Event</code>, <code>X-Soscom-Delivery-Id</code>,{' '}
        <code>X-Soscom-Signature: t=TIMESTAMP,v1=HEX</code>. Sign{' '}
        <code>TIMESTAMP + '.' + raw_body</code> with HMAC-SHA256 and your
        webhook secret. Failed deliveries are retried with backoff.
      </p>
      <CodeBlock>{`{
  "id": "evt_01h…",
  "type": "delivery.updated",
  "created_at": "2026-09-20T15:01:00+03:00",
  "data": {
    "id": 41,
    "campaign_id": 21,
    "to": "+255712445221",
    "status": "delivered",
    "sender_id": "MWALIKO",
    "delivered_at": "2026-09-20T15:01:00+03:00"
  }
}`}</CodeBlock>
    </DocSection>
  )
}

function ErrorsSection() {
  return (
    <DocSection id='error-codes' title='Error codes'>
      <ul className='space-y-2'>
        <ErrorRow code='401' detail='Missing, invalid, or revoked API key (`invalid_api_key`).' />
        <ErrorRow code='402' detail='Not enough SMS credits.' />
        <ErrorRow code='403' detail='Sender ID is not on this account, or the account is suspended / inactive.' />
        <ErrorRow code='404' detail='Campaign or message not found.' />
        <ErrorRow code='409' detail='Idempotency-Key already used with a different body.' />
        <ErrorRow code='422' detail='Invalid Tanzanian number, empty message, or bulk `to` was not an array.' />
        <ErrorRow code='429' detail='More than 60 requests in a minute for this key.' />
      </ul>
    </DocSection>
  )
}

function DocSection({
  id,
  title,
  children,
}: {
  id: SectionId
  title: string
  children: ReactNode
}) {
  return (
    <section
      id={id}
      className='scroll-mt-8 space-y-4 rounded-xl border bg-card p-6'
    >
      <h2 className='text-xl font-semibold tracking-tight'>{title}</h2>
      <div className='space-y-3 text-sm leading-6 text-muted-foreground'>
        {children}
      </div>
    </section>
  )
}

function Endpoint({ method, path }: { method: 'GET' | 'POST'; path: string }) {
  return (
    <div className='flex flex-wrap items-center gap-2 font-mono text-sm text-foreground'>
      <span
        className={cn(
          'rounded px-1.5 py-0.5 text-[11px] font-semibold tracking-wide',
          method === 'GET'
            ? 'bg-muted text-foreground'
            : 'bg-primary/15 text-primary'
        )}
      >
        {method}
      </span>
      <span>{path}</span>
    </div>
  )
}

function Callout({ children }: { children: ReactNode }) {
  return (
    <div className='rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-foreground'>
      {children}
    </div>
  )
}

function ErrorRow({ code, detail }: { code: string; detail: string }) {
  return (
    <li className='flex gap-3 text-sm'>
      <span className='w-10 shrink-0 font-mono font-semibold text-foreground'>
        {code}
      </span>
      <span>{detail}</span>
    </li>
  )
}

function CodeBlock({ children }: { children: string }) {
  const [copied, setCopied] = useState(false)

  return (
    <div className='relative'>
      <pre className='overflow-x-auto rounded-lg bg-muted p-4 pr-12 text-[13px] leading-5 text-foreground'>
        {children}
      </pre>
      <button
        type='button'
        className='absolute top-2 right-2 rounded-md p-1.5 text-muted-foreground hover:bg-background hover:text-foreground'
        onClick={async () => {
          await navigator.clipboard.writeText(children)
          setCopied(true)
          window.setTimeout(() => setCopied(false), 1500)
        }}
        aria-label='Copy'
      >
        {copied ? <Check className='size-3.5' /> : <Copy className='size-3.5' />}
      </button>
    </div>
  )
}

function CodeExamples({
  example,
  base,
}: {
  example: 'send' | 'bulk' | 'balance'
  base: string
}) {
  const snippets = examples(base)

  return (
    <Tabs defaultValue='curl' className='mt-2'>
      <TabsList>
        <TabsTrigger value='curl'>cURL</TabsTrigger>
        <TabsTrigger value='php'>PHP</TabsTrigger>
        <TabsTrigger value='javascript'>JavaScript</TabsTrigger>
        <TabsTrigger value='python'>Python</TabsTrigger>
      </TabsList>
      {(['curl', 'php', 'javascript', 'python'] as const).map((lang) => (
        <TabsContent key={lang} value={lang}>
          <CodeBlock>{snippets[lang][example]}</CodeBlock>
        </TabsContent>
      ))}
    </Tabs>
  )
}
