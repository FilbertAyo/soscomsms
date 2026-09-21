import { useState } from 'react'
import { Link } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors, meQueryKey, updateProfileRequest } from '@/lib/auth'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function CompleteProfileCard() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const [business, setBusiness] = useState(user?.customer?.businessName ?? '')
  const [phone, setPhone] = useState(user?.customer?.phone ?? '')
  const [saving, setSaving] = useState(false)

  return (
    <div className='rounded-xl border bg-card p-5'>
      <h2 className='text-lg font-semibold'>Complete your profile</h2>
      <p className='mt-1 text-sm text-muted-foreground'>
        Add your business name and a Tanzanian phone number to unlock Send SMS
        and the rest of the dashboard.
      </p>
      <form
        className='mt-5 grid gap-4 sm:grid-cols-2'
        onSubmit={async (event) => {
          event.preventDefault()
          setSaving(true)
          try {
            const updated = await updateProfileRequest({
              business_name: business,
              phone,
            })
            useAuthStore.getState().setUser(updated)
            queryClient.setQueryData(meQueryKey, updated)
            toast.success('Profile complete. You can send SMS now.')
          } catch (error) {
            toast.error(applyValidationErrors(error, () => undefined))
          } finally {
            setSaving(false)
          }
        }}
      >
        <div className='space-y-2'>
          <Label htmlFor='onboarding-business'>Business name</Label>
          <Input
            id='onboarding-business'
            value={business}
            onChange={(event) => setBusiness(event.target.value)}
            required
          />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='onboarding-phone'>Phone</Label>
          <Input
            id='onboarding-phone'
            value={phone}
            placeholder='0754 221 890'
            onChange={(event) => setPhone(event.target.value)}
            required
          />
        </div>
        <div className='flex items-center gap-3 sm:col-span-2'>
          <Button type='submit' disabled={saving}>
            {saving ? 'Saving…' : 'Save and continue'}
          </Button>
          <Button variant='ghost' asChild>
            <Link to='/settings/business'>Open settings</Link>
          </Button>
        </div>
      </form>
    </div>
  )
}
