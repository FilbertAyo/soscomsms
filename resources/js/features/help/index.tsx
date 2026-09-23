import { useMemo, useState } from 'react'
import { Link } from '@tanstack/react-router'
import { toast } from 'sonner'
import { faqs } from '@/data/sms'
import { developerApiVisible } from '@/lib/features'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'

const supportEmail = 'sms@soscom.co.tz'

export function HelpPage() {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [subject, setSubject] = useState('')
  const [message, setMessage] = useState('')

  const filtered = useMemo(() => {
    const value = query.toLowerCase()
    const source = developerApiVisible
      ? faqs
      : faqs.filter((category) => category.category !== 'API')
    return source
      .map((category) => ({
        ...category,
        items: category.items.filter(
          (item) =>
            item.q.toLowerCase().includes(value) ||
            item.a.toLowerCase().includes(value) ||
            category.category.toLowerCase().includes(value)
        ),
      }))
      .filter((category) => category.items.length > 0)
  }, [query])

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='How can we help?'
          description='Search common questions, or email Soscom if you still need help.'
          actions={
            <Button onClick={() => setOpen(true)}>Contact Support</Button>
          }
        />
        <div className='mb-8 flex flex-col gap-3 sm:flex-row sm:items-center'>
          <Input
            className='max-w-lg'
            placeholder='Search for help...'
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <p className='text-sm text-muted-foreground'>
            Email{' '}
            <a
              href={`mailto:${supportEmail}`}
              className='font-medium text-primary hover:underline'
            >
              {supportEmail}
            </a>
            {developerApiVisible ? (
              <>
                {' · '}
                <Link
                  to='/developer/docs'
                  className='font-medium text-primary hover:underline'
                >
                  API documentation
                </Link>
              </>
            ) : null}
          </p>
        </div>
        <div className='space-y-8'>
          {filtered.map((category) => (
            <section key={category.category}>
              <h2 className='mb-3 text-lg font-semibold'>{category.category}</h2>
              <div className='grid gap-3 md:grid-cols-2'>
                {category.items.map((item) => (
                  <div key={item.q} className='rounded-xl border bg-card p-5'>
                    <h3 className='font-medium'>{item.q}</h3>
                    <p className='mt-2 text-sm text-muted-foreground'>
                      {item.a}
                    </p>
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      </Main>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Contact Support</DialogTitle>
          </DialogHeader>
          <p className='text-sm text-muted-foreground'>
            This opens your email app to {supportEmail}.
          </p>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='support-subject'>Subject</Label>
              <Input
                id='support-subject'
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='support-message'>Message</Label>
              <Textarea
                id='support-message'
                value={message}
                onChange={(event) => setMessage(event.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              onClick={() => {
                if (subject.trim() === '' || message.trim() === '') {
                  toast.error('Add a subject and a message.')
                  return
                }

                const href = `mailto:${supportEmail}?subject=${encodeURIComponent(subject.trim())}&body=${encodeURIComponent(message.trim())}`
                window.location.href = href
                setOpen(false)
                setSubject('')
                setMessage('')
              }}
            >
              Open email
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
