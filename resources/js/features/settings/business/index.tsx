import { useState } from 'react'
import { toast } from 'sonner'
import { useQueryClient } from '@tanstack/react-query'
import { applyValidationErrors, meQueryKey, updateProfileRequest } from '@/lib/auth'
import { useAuthStore } from '@/stores/auth-store'
import { needsProfileCompletion } from '@/types/auth'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ContentSection } from '../components/content-section'

const JOB_ROLES = [
  'Owner',
  'Director',
  'Manager',
  'Administrator',
  'IT / Technical',
  'Marketing',
  'Operations',
  'Finance',
  'Teacher / Head Teacher',
  'Other',
] as const

export function SettingsBusiness() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const [business, setBusiness] = useState(user?.customer?.businessName ?? '')
  const [role, setRole] = useState(user?.customer?.jobTitle ?? '')
  const [phone, setPhone] = useState(user?.customer?.phone ?? '')
  const [saving, setSaving] = useState(false)
  const incomplete = user ? needsProfileCompletion(user) : false

  if (user?.role === 'admin') {
    return (
      <ContentSection
        title='Business Information'
        desc='Admin accounts do not have a customer business profile.'
      >
        <p className='text-sm text-muted-foreground'>
          Switch to a customer account to manage business details.
        </p>
      </ContentSection>
    )
  }

  return (
    <ContentSection
      title='Business Information'
      desc={
        incomplete
          ? 'Add your business name and Tanzanian phone number to unlock Send SMS.'
          : 'Used on Sender ID requests and invoices.'
      }
    >
      <form
        className='space-y-4'
        onSubmit={async (event) => {
          event.preventDefault()
          setSaving(true)
          try {
            const updated = await updateProfileRequest({
              business_name: business.trim() === '' ? null : business,
              job_title: role.trim() === '' ? null : role,
              phone: phone.trim() === '' ? null : phone,
            })
            useAuthStore.getState().setUser(updated)
            queryClient.setQueryData(meQueryKey, updated)
            toast.success(
              needsProfileCompletion(updated)
                ? 'Saved. Add both a business name and phone to start sending SMS.'
                : 'Business information saved. You can send SMS now.'
            )
          } catch (error) {
            toast.error(applyValidationErrors(error, () => undefined))
          } finally {
            setSaving(false)
          }
        }}
      >
        <div className='space-y-2'>
          <Label htmlFor='business'>Business / organization name</Label>
          <Input
            id='business'
            value={business}
            onChange={(event) => setBusiness(event.target.value)}
            required
          />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='phone'>Business phone</Label>
          <Input
            id='phone'
            value={phone}
            placeholder='+255 754 221 890'
            onChange={(event) => setPhone(event.target.value)}
            required
          />
         
        </div>
        <div className='space-y-2'>
          <Label htmlFor='role'>Your role</Label>
          <Select
            value={role === '' ? undefined : role}
            onValueChange={setRole}
          >
            <SelectTrigger id='role' className='w-full'>
              <SelectValue placeholder='Select your role' />
            </SelectTrigger>
            <SelectContent>
              {JOB_ROLES.map((jobRole) => (
                <SelectItem key={jobRole} value={jobRole}>
                  {jobRole}
                </SelectItem>
              ))}
              {role !== '' &&
              !(JOB_ROLES as readonly string[]).includes(role) ? (
                <SelectItem value={role}>{role}</SelectItem>
              ) : null}
            </SelectContent>
          </Select>
        </div>
        <Button type='submit' disabled={saving}>
          {saving ? 'Saving…' : 'Save business details'}
        </Button>
      </form>
    </ContentSection>
  )
}
