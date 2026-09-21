import { useState } from 'react'
import { toast } from 'sonner'
import { applyValidationErrors, updatePasswordRequest } from '@/lib/auth'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { PasswordInput } from '@/components/password-input'
import { ContentSection } from '../components/content-section'

export function SettingsPassword() {
  const [current, setCurrent] = useState('')
  const [next, setNext] = useState('')
  const [confirm, setConfirm] = useState('')
  const [saving, setSaving] = useState(false)

  return (
    <ContentSection
      title='Password'
      desc='Choose a password that only you know.'
    >
      <form
        className='space-y-4'
        onSubmit={async (event) => {
          event.preventDefault()
          if (next.length < 8) {
            toast.error('Your new password must be at least 8 characters.')
            return
          }
          if (next !== confirm) {
            toast.error('The new passwords do not match.')
            return
          }
          setSaving(true)
          try {
            const message = await updatePasswordRequest({
              current_password: current,
              password: next,
              password_confirmation: confirm,
            })
            setCurrent('')
            setNext('')
            setConfirm('')
            toast.success(message)
          } catch (error) {
            toast.error(applyValidationErrors(error, () => undefined))
          } finally {
            setSaving(false)
          }
        }}
      >
        <div className='space-y-2'>
          <Label htmlFor='current'>Current password</Label>
          <PasswordInput
            id='current'
            value={current}
            onChange={(event) => setCurrent(event.target.value)}
          />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='next'>New password</Label>
          <PasswordInput
            id='next'
            value={next}
            onChange={(event) => setNext(event.target.value)}
          />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='confirm'>Confirm new password</Label>
          <PasswordInput
            id='confirm'
            value={confirm}
            onChange={(event) => setConfirm(event.target.value)}
          />
        </div>
        <Button type='submit' disabled={saving}>
          {saving ? 'Updating…' : 'Update password'}
        </Button>
      </form>
    </ContentSection>
  )
}
