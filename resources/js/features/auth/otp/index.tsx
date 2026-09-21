import { Link, useSearch } from '@tanstack/react-router'
import { useState } from 'react'
import { toast } from 'sonner'
import { resendOtpRequest } from '@/lib/auth'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { AuthLayout } from '../auth-layout'
import { OtpForm } from './components/otp-form'

export function Otp() {
  const { challenge, email, redirect } = useSearch({ from: '/(auth)/otp' })
  const [currentChallenge, setCurrentChallenge] = useState(challenge ?? '')
  const [resending, setResending] = useState(false)

  async function resend() {
    if (currentChallenge === '' || resending) {
      return
    }

    setResending(true)
    try {
      const next = await resendOtpRequest(currentChallenge)
      setCurrentChallenge(next.challenge)
      toast.success('A new code was sent to your email.')
    } catch {
      toast.error('Could not send a new code. Sign in again.')
    } finally {
      setResending(false)
    }
  }

  return (
    <AuthLayout>
      <Card className='max-w-md gap-4'>
        <CardHeader>
          <CardTitle className='text-lg tracking-tight'>
            Enter the email code
          </CardTitle>
          <CardDescription>
            We sent a 6-digit code to {email || 'your email'}. Enter it to
            finish signing in.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <OtpForm challenge={currentChallenge} redirectTo={redirect} />
        </CardContent>
        <CardFooter>
          <p className='px-8 text-center text-sm text-muted-foreground'>
            Did not get it?{' '}
            <button
              type='button'
              className='underline underline-offset-4 hover:text-primary'
              onClick={() => void resend()}
              disabled={resending || currentChallenge === ''}
            >
              {resending ? 'Sending…' : 'Send a new code'}
            </button>
            {' · '}
            <Link
              to='/sign-in'
              className='underline underline-offset-4 hover:text-primary'
            >
              Back to sign in
            </Link>
          </p>
        </CardFooter>
      </Card>
    </AuthLayout>
  )
}
