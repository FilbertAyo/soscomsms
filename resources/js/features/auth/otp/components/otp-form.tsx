import { useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { meQueryKey, validationMessage, verifyOtpRequest } from '@/lib/auth'
import { safeInternalPath } from '@/lib/session'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSlot,
  InputOTPSeparator,
} from '@/components/ui/input-otp'

const formSchema = z.object({
  otp: z
    .string()
    .min(6, 'Enter the 6-digit code.')
    .max(6, 'Enter the 6-digit code.'),
})

export function OtpForm({
  className,
  challenge,
  redirectTo,
  ...props
}: React.HTMLAttributes<HTMLFormElement> & {
  challenge: string
  redirectTo?: string
}) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [isLoading, setIsLoading] = useState(false)

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: { otp: '' },
  })

  const otp = form.watch('otp')

  async function onSubmit(data: z.infer<typeof formSchema>) {
    if (challenge === '') {
      toast.error('This code expired. Sign in again.')
      navigate({ to: '/sign-in', replace: true })
      return
    }

    setIsLoading(true)
    try {
      const user = await verifyOtpRequest(challenge, data.otp)
      useAuthStore.getState().setUser(user)
      queryClient.setQueryData(meQueryKey, user)
      navigate({ to: safeInternalPath(redirectTo), replace: true })
      toast.success(`Welcome back, ${user.name}.`)
    } catch (error) {
      const message =
        validationMessage(error, 'otp') ?? 'Could not verify that code.'
      form.setError('otp', { message })
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className={cn('grid gap-2', className)}
        {...props}
      >
        <FormField
          control={form.control}
          name='otp'
          render={({ field }) => (
            <FormItem>
              <FormLabel className='sr-only'>One-time code</FormLabel>
              <FormControl>
                <InputOTP
                  maxLength={6}
                  {...field}
                  containerClassName='justify-between sm:[&>[data-slot="input-otp-group"]>div]:w-12'
                >
                  <InputOTPGroup>
                    <InputOTPSlot index={0} />
                    <InputOTPSlot index={1} />
                  </InputOTPGroup>
                  <InputOTPSeparator />
                  <InputOTPGroup>
                    <InputOTPSlot index={2} />
                    <InputOTPSlot index={3} />
                  </InputOTPGroup>
                  <InputOTPSeparator />
                  <InputOTPGroup>
                    <InputOTPSlot index={4} />
                    <InputOTPSlot index={5} />
                  </InputOTPGroup>
                </InputOTP>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2' disabled={otp.length < 6 || isLoading}>
          {isLoading ? 'Checking…' : 'Verify'}
        </Button>
      </form>
    </Form>
  )
}
