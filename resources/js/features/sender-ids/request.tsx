import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors } from '@/lib/auth'
import {
  createSenderIdRequest,
  senderIdRequestQueryKey,
  senderIdRequestsQueryKey,
} from '@/lib/sender-ids'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { AppHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'

export function RequestSenderId() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const businessName =
    useAuthStore((state) => state.user?.customer?.businessName) ?? ''
  const [name, setName] = useState('')
  const [purpose, setPurpose] = useState('')
  const [organization, setOrganization] = useState(businessName)
  const [description, setDescription] = useState('')

  const submitMutation = useMutation({
    mutationFn: () =>
      createSenderIdRequest({
        name: name.trim(),
        purpose: purpose.trim(),
        organization: organization.trim(),
        description: description.trim() || undefined,
      }),
    onSuccess: async (request) => {
      toast.success('Sender ID request submitted.')
      void queryClient.invalidateQueries({ queryKey: senderIdRequestsQueryKey })
      void queryClient.invalidateQueries({
        queryKey: senderIdRequestQueryKey(request.id),
      })
      await navigate({
        to: '/sender-ids/$senderId',
        params: { senderId: String(request.id) },
      })
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
          title='Request Sender ID'
          description='Ask to send SMS using your own business or organization name.'
        />
        <form
          className='max-w-xl space-y-5 rounded-xl border bg-card p-5'
          onSubmit={(event) => {
            event.preventDefault()
            if (!name.trim() || !purpose.trim() || !organization.trim()) {
              toast.error('Please complete the required fields.')
              return
            }
            submitMutation.mutate()
          }}
        >
          <div className='space-y-2'>
            <Label htmlFor='sender-id'>Sender ID</Label>
            <Input
              id='sender-id'
              value={name}
              onChange={(event) =>
                setName(event.target.value.replaceAll(/\s+/g, ''))
              }
              maxLength={13}
            />
            <p className='text-xs text-muted-foreground'>
              3 to 13 letters or numbers. Spaces are not allowed.
            </p>
          </div>
          <div className='space-y-2'>
            <Label htmlFor='purpose'>Purpose</Label>
            <Input
              id='purpose'
              value={purpose}
              onChange={(event) => setPurpose(event.target.value)}
            />
          </div>
          <div className='space-y-2'>
            <Label htmlFor='org'>Business / Organization Name</Label>
            <Input
              id='org'
              value={organization}
              onChange={(event) => setOrganization(event.target.value)}
            />
          </div>
          <div className='space-y-2'>
            <Label htmlFor='description'>Description</Label>
            <Textarea
              id='description'
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </div>
          <p className='text-sm text-muted-foreground'>
            Sender IDs usually require approval before they can be used.
          </p>
          <Button type='submit' disabled={submitMutation.isPending}>
            {submitMutation.isPending ? 'Submitting…' : 'Submit Request'}
          </Button>
        </form>
      </Main>
    </>
  )
}
