import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { toast } from 'sonner'
import { Copy, KeyRound } from 'lucide-react'
import { applyValidationErrors } from '@/lib/auth'
import {
  apiKeysQueryKey,
  copyText,
  createApiKey,
  fetchApiKeys,
  revokeApiKey,
} from '@/lib/api-keys'
import { formatDateTime } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
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
import { type ApiKeyRecord } from '@/types/api-keys'

export function DeveloperPage() {
  const queryClient = useQueryClient()
  const [createOpen, setCreateOpen] = useState(false)
  const [name, setName] = useState('')
  const [plaintext, setPlaintext] = useState<string | null>(null)
  const [revokeTarget, setRevokeTarget] = useState<ApiKeyRecord | null>(null)

  const keysQuery = useQuery({
    queryKey: apiKeysQueryKey,
    queryFn: fetchApiKeys,
  })
  const keys = keysQuery.data ?? []
  const activeCount = keys.filter((key) => key.status === 'active').length

  const createMutation = useMutation({
    mutationFn: () => createApiKey(name.trim()),
    onSuccess: async (result) => {
      setCreateOpen(false)
      setName('')
      setPlaintext(result.key)
      toast.success('API key created. Copy it now — it will not be shown again.')
      void queryClient.invalidateQueries({ queryKey: apiKeysQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  const revokeMutation = useMutation({
    mutationFn: (id: number) => revokeApiKey(id),
    onSuccess: async () => {
      setRevokeTarget(null)
      toast.success('API key revoked.')
      void queryClient.invalidateQueries({ queryKey: apiKeysQueryKey })
    },
    onError: (error) => {
      toast.error(applyValidationErrors(error, () => undefined))
    },
  })

  return (
    <>
      <AppHeader />
      <Main>
        <PageHeader
          title='API Access'
          description='Connect your website or application to Soscom SMS. Keys are shown in full only once.'
          actions={
            <>
              <Button variant='outline' asChild>
                <Link to='/developer/docs'>View Documentation</Link>
              </Button>
              <Button onClick={() => setCreateOpen(true)}>Create API Key</Button>
            </>
          }
        />
        <div className='mb-6 rounded-xl border bg-card p-5'>
          <p className='text-sm text-muted-foreground'>API Status</p>
          {keysQuery.isLoading ? (
            <>
              <Skeleton className='mt-2 h-6 w-28' />
              <Skeleton className='mt-2 h-4 w-72 max-w-full' />
            </>
          ) : (
            <>
              <p className='mt-1 text-lg font-semibold'>
                {activeCount > 0 ? 'Live' : 'No active keys'}
              </p>
              <p className='mt-1 text-sm text-muted-foreground'>
                {activeCount > 0
                  ? `${activeCount} active key${activeCount === 1 ? '' : 's'} can send SMS through /api/v1.`
                  : 'Create a key to send SMS from your application.'}
              </p>
            </>
          )}
        </div>
        {keysQuery.isError ? (
          <p className='text-sm text-destructive'>Could not load API keys.</p>
        ) : keysQuery.isLoading ? (
          <TableSkeleton rows={3} />
        ) : keys.length === 0 ? (
          <EmptyState
            icon={KeyRound}
            title='No API keys yet'
            description='Create a key, copy it once, then use it as a Bearer token on /api/v1.'
            actionLabel='Create API Key'
            onAction={() => setCreateOpen(true)}
          />
        ) : (
          <TableWrap>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>API Key</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Last used</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className='text-end'>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {keys.map((key) => (
                  <TableRow key={key.id}>
                    <TableCell className='font-medium'>{key.name}</TableCell>
                    <TableCell className='font-mono text-sm'>
                      {key.masked}
                    </TableCell>
                    <TableCell>
                      {key.createdAt ? formatDateTime(key.createdAt) : '—'}
                    </TableCell>
                    <TableCell>
                      {key.lastUsedAt ? formatDateTime(key.lastUsedAt) : 'Never'}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={key.status} />
                    </TableCell>
                    <TableCell className='text-end'>
                      {key.status === 'active' ? (
                        <Button
                          variant='ghost'
                          size='sm'
                          onClick={() => setRevokeTarget(key)}
                        >
                          Revoke
                        </Button>
                      ) : null}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableWrap>
        )}
      </Main>
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create API Key</DialogTitle>
          </DialogHeader>
          <div className='space-y-2'>
            <Label htmlFor='key-name'>Key name</Label>
            <Input
              id='key-name'
              placeholder='e.g. Production website'
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setCreateOpen(false)}>
              Cancel
            </Button>
            <Button
              disabled={createMutation.isPending || name.trim().length < 2}
              onClick={() => createMutation.mutate()}
            >
              {createMutation.isPending ? 'Creating…' : 'Create API Key'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog
        open={plaintext !== null}
        onOpenChange={(open) => {
          if (!open) {
            setPlaintext(null)
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Copy your API key</DialogTitle>
          </DialogHeader>
          <p className='text-sm text-muted-foreground'>
            This is the only time the full key is shown. Store it in your
            application secrets. Refreshing this page will only show a masked
            value.
          </p>
          <div className='flex gap-2'>
            <Input
              readOnly
              value={plaintext ?? ''}
              className='font-mono text-sm'
            />
            <Button
              type='button'
              variant='outline'
              onClick={async () => {
                if (plaintext && (await copyText(plaintext))) {
                  toast.success('API key copied.')
                } else {
                  toast.error('Could not copy. Select the key and copy it manually.')
                }
              }}
            >
              <Copy className='size-4' />
              Copy
            </Button>
          </div>
          <DialogFooter>
            <Button onClick={() => setPlaintext(null)}>I have copied this key</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <ConfirmDialog
        open={revokeTarget !== null}
        onOpenChange={() => setRevokeTarget(null)}
        title='Revoke this API key?'
        desc='Applications using this key will stop sending SMS until you create a new key.'
        confirmText={revokeMutation.isPending ? 'Revoking…' : 'Revoke'}
        destructive
        isLoading={revokeMutation.isPending}
        handleConfirm={() => {
          if (revokeTarget) {
            revokeMutation.mutate(revokeTarget.id)
          }
        }}
      />
    </>
  )
}
