import { useState } from 'react'
import { toast } from 'sonner'
import { useQueryClient } from '@tanstack/react-query'
import { applyValidationErrors, meQueryKey, updateProfileRequest } from '@/lib/auth'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ContentSection } from '../components/content-section'

export function SettingsProfile() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const [name, setName] = useState(user?.name ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [phone, setPhone] = useState(user?.customer?.phone ?? '')
  const [saving, setSaving] = useState(false)

  return (
    <ContentSection
      title='Profile'
      desc='This is how your name and contact details appear on Soscom SMS.'
    >
      <form
        className='space-y-4'
        onSubmit={async (event) => {
          event.preventDefault()
          setSaving(true)
          try {
            const updated = await updateProfileRequest({
              name,
              email,
              phone: phone.trim() === '' ? null : phone,
            })
            useAuthStore.getState().setUser(updated)
            queryClient.setQueryData(meQueryKey, updated)
            toast.success('Profile saved.')
          } catch (error) {
            toast.error(applyValidationErrors(error, () => undefined))
          } finally {
            setSaving(false)
          }
        }}
      >
        <div className='space-y-2'>
          <Label htmlFor='name'>Full name</Label>
          <Input
            id='name'
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='email'>Email</Label>
          <Input
            id='email'
            type='email'
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
        </div>
        {user?.role === 'customer' ? (
          <div className='space-y-2'>
            <Label htmlFor='phone'>Phone number</Label>
            <Input
              id='phone'
              value={phone}
              placeholder='+255 754 221 890'
              onChange={(event) => setPhone(event.target.value)}
            />
            <p className='text-xs text-muted-foreground'>
              Tanzanian mobile numbers only. 07…, 06…, or +255 are accepted.
            </p>
          </div>
        ) : null}
        <Button type='submit' disabled={saving}>
          {saving ? 'Saving…' : 'Save profile'}
        </Button>
      </form>
    </ContentSection>
  )
}
