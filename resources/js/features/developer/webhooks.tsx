import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Copy, Webhook } from 'lucide-react'
import { applyValidationErrors } from '@/lib/auth'
import { copyText } from '@/lib/api-keys'
import { formatDateTime } from '@/lib/format'
import {
  WEBHOOK_EVENTS,
  createWebhook,
  deleteWebhook,
  fetchWebhookDeliveries,
  fetchWebhooks,
  rotateWebhookSecret,
  updateWebhook,
  webhookDeliveriesQueryKey,
  webhookEventLabel,
  webhooksQueryKey,
} from '@/lib/webhooks'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AppHeader } from '@/components/soscom/app-header'
import { EmptyState } from '@/components/soscom/empty-state'
import { PageHeader } from '@/components/soscom/page-header'
import { TableSkeleton } from '@/components/soscom/skeletons'
import { StatusBadge } from '@/components/soscom/status-badge'
import { TableWrap } from '@/components/soscom/table-wrap'
import { Main } from '@/components/layout/main'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  type WebhookEvent,
  type WebhookRecord,
} from '@/types/webhooks'

export function WebhooksPage() {
  const queryClient = useQueryClient()
  const [createOpen, setCreateOpen] = useState(false)
  const [name, setName] = useState('Delivery webhook')
  const [url, setUrl] = useState('')
  const [events, setEvents] = useState<WebhookEvent[]>([...WEBHOOK_EVENTS])
  const [secret, setSecret] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<WebhookRecord | null>(null)
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const webhooksQuery = useQuery({
    queryKey: webhooksQueryKey,
    queryFn: fetchWebhooks,
  })
  const webhooks = webhooksQuery.data ?? []
  const active = selectedId ?? webhooks[0]?.id ?? null

  const deliveriesQuery = useQuery({
    queryKey: webhookDeliveriesQueryKey(active ?? 0),
    queryFn: () => fetchWebhookDeliveries(active ?? 0),
    enabled: active !== null,
  })

  const createMutation = useMutation({
    mutationFn: () => createWebhook({ name: name.trim(), url: url.trim(), events }),
    onSuccess: async (result) => {
      setCreateOpen(false)
      setUrl('')
      setSecret(result.secret)
      toast.success('Webhook created. Copy the signing secret now.')
      void queryClient.invalidateQueries({ queryKey: webhooksQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const toggleMutation = useMutation({
    mutationFn: (webhook: WebhookRecord) =>
      updateWebhook(webhook.id, { enabled: !webhook.enabled }),
    onSuccess: async () => {
      void queryClient.invalidateQueries({ queryKey: webhooksQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const rotateMutation = useMutation({
    mutationFn: (id: number) => rotateWebhookSecret(id),
    onSuccess: async (result) => {
      setSecret(result.secret)
      toast.success('New signing secret created. Copy it now.')
      void queryClient.invalidateQueries({ queryKey: webhooksQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteWebhook(id),
    onSuccess: async () => {
      setDeleteTarget(null)
      toast.success('Webhook removed.')
      void queryClient.invalidateQueries({ queryKey: webhooksQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  function toggleEvent(event: WebhookEvent) {
    setEvents((current) =>
      current.includes(event)
        ? current.filter((item) => item !== event)
        : [...current, event]
    )
  }

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='Webhooks'
          description='Receive signed POSTs when a delivery updates or a campaign finishes.'
          actions={
            <Button onClick={() => setCreateOpen(true)}>Add webhook</Button>
          }
        />
        {webhooksQuery.isError ? (
          <p className='text-sm text-destructive'>Could not load webhooks.</p>
        ) : webhooksQuery.isLoading ? (
          <TableSkeleton rows={3} />
        ) : webhooks.length === 0 ? (
          <EmptyState
            icon={Webhook}
            title='No webhooks yet'
            description='Add an HTTPS URL. Soscom SMS will POST delivery.updated and campaign.completed events with an HMAC signature.'
            actionLabel='Add webhook'
            onAction={() => setCreateOpen(true)}
          />
        ) : (
          <div className='space-y-8'>
            <TableWrap>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>URL</TableHead>
                    <TableHead>Events</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className='text-end'>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {webhooks.map((webhook) => (
                    <TableRow
                      key={webhook.id}
                      className={active === webhook.id ? 'bg-muted/40' : undefined}
                    >
                      <TableCell>
                        <button
                          type='button'
                          className='font-medium hover:underline'
                          onClick={() => setSelectedId(webhook.id)}
                        >
                          {webhook.name}
                        </button>
                      </TableCell>
                      <TableCell className='max-w-xs truncate font-mono text-xs'>
                        {webhook.url}
                      </TableCell>
                      <TableCell className='text-sm text-muted-foreground'>
                        {webhook.events.map(webhookEventLabel).join(', ')}
                      </TableCell>
                      <TableCell>
                        <StatusBadge
                          status={webhook.enabled ? 'active' : 'inactive'}
                        />
                      </TableCell>
                      <TableCell className='space-x-2 text-end'>
                        <Button
                          variant='ghost'
                          size='sm'
                          onClick={() => toggleMutation.mutate(webhook)}
                        >
                          {webhook.enabled ? 'Disable' : 'Enable'}
                        </Button>
                        <Button
                          variant='ghost'
                          size='sm'
                          onClick={() => rotateMutation.mutate(webhook.id)}
                        >
                          Rotate secret
                        </Button>
                        <Button
                          variant='ghost'
                          size='sm'
                          onClick={() => setDeleteTarget(webhook)}
                        >
                          Delete
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableWrap>
            <section>
              <h2 className='mb-3 text-lg font-semibold'>Recent deliveries</h2>
              {deliveriesQuery.isLoading ? (
                <TableSkeleton rows={4} />
              ) : (
              <TableWrap>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>When</TableHead>
                      <TableHead>Event</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Attempt</TableHead>
                      <TableHead>HTTP</TableHead>
                      <TableHead>Next retry</TableHead>
                      <TableHead>Error</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(deliveriesQuery.data ?? []).length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className='text-muted-foreground'>
                          No webhook posts yet. Send an SMS to see delivery.updated.
                        </TableCell>
                      </TableRow>
                    ) : (
                      (deliveriesQuery.data ?? []).map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>
                            {row.createdAt ? formatDateTime(row.createdAt) : '—'}
                          </TableCell>
                          <TableCell>{webhookEventLabel(row.event)}</TableCell>
                          <TableCell>
                            <StatusBadge status={row.status} />
                          </TableCell>
                          <TableCell>{row.attempt}</TableCell>
                          <TableCell>{row.httpStatus ?? '—'}</TableCell>
                          <TableCell>
                            {row.nextRetryAt
                              ? formatDateTime(row.nextRetryAt)
                              : '—'}
                          </TableCell>
                          <TableCell className='max-w-xs truncate text-sm text-muted-foreground'>
                            {row.error ?? '—'}
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </TableWrap>
              )}
            </section>
          </div>
        )}
      </Main>
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add webhook</DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='webhook-name'>Name</Label>
              <Input
                id='webhook-name'
                value={name}
                onChange={(event) => setName(event.target.value)}
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='webhook-url'>HTTPS URL</Label>
              <Input
                id='webhook-url'
                placeholder='https://example.com/webhooks/soscom'
                value={url}
                onChange={(event) => setUrl(event.target.value)}
              />
            </div>
            <div className='space-y-2'>
              <p className='text-sm font-medium'>Events</p>
              {WEBHOOK_EVENTS.map((event) => (
                <label key={event} className='flex items-center gap-2 text-sm'>
                  <Checkbox
                    checked={events.includes(event)}
                    onCheckedChange={() => toggleEvent(event)}
                  />
                  {webhookEventLabel(event)}
                </label>
              ))}
            </div>
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button
              disabled={
                createMutation.isPending ||
                name.trim().length < 2 ||
                url.trim().length < 8 ||
                events.length === 0
              }
              onClick={() => createMutation.mutate()}
            >
              {createMutation.isPending ? 'Saving…' : 'Save webhook'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog
        open={secret !== null}
        onOpenChange={(open) => {
          if (!open) {
            setSecret(null)
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Copy the signing secret</DialogTitle>
          </DialogHeader>
          <p className='text-sm text-muted-foreground'>
            Verify X-Soscom-Signature with HMAC-SHA256 of timestamp + body. This
            secret is shown only now.
          </p>
          <div className='flex gap-2'>
            <Input readOnly value={secret ?? ''} className='font-mono text-sm' />
            <Button
              type='button'
              variant='outline'
              onClick={async () => {
                if (secret && (await copyText(secret))) {
                  toast.success('Secret copied.')
                }
              }}
            >
              <Copy className='size-4' />
              Copy
            </Button>
          </div>
          <DialogFooter>
            <Button onClick={() => setSecret(null)}>I have copied this secret</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <ConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={() => setDeleteTarget(null)}
        title='Delete this webhook?'
        desc='Soscom SMS will stop posting events to this URL.'
        confirmText={deleteMutation.isPending ? 'Deleting…' : 'Delete'}
        destructive
        isLoading={deleteMutation.isPending}
        handleConfirm={() => {
          if (deleteTarget) {
            deleteMutation.mutate(deleteTarget.id)
          }
        }}
      />
    </>
  )
}
