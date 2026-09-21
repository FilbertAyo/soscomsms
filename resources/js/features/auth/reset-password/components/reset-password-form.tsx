import { useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2 } from 'lucide-react'
import { useNavigate } from '@tanstack/react-router'
import { toast } from 'sonner'
import { applyValidationErrors, resetPasswordRequest } from '@/lib/auth'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { PasswordInput } from '@/components/password-input'

const formSchema = z
  .object({
    password: z
      .string()
      .min(1, 'Please enter a new password.')
      .min(8, 'Password must be at least 8 characters long.'),
    confirmPassword: z.string().min(1, 'Please confirm your password.'),
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "Passwords don't match.",
    path: ['confirmPassword'],
  })

type ResetPasswordFormProps = React.HTMLAttributes<HTMLFormElement> & {
  token?: string
  email?: string
}

export function ResetPasswordForm({
  className,
  token,
  email,
  ...props
}: ResetPasswordFormProps) {
  const [isLoading, setIsLoading] = useState(false)
  const navigate = useNavigate()

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      password: '',
      confirmPassword: '',
    },
  })

  async function onSubmit(data: z.infer<typeof formSchema>) {
    if (!token || !email) {
      toast.error('This reset link is missing a token or email.')
      return
    }

    setIsLoading(true)
    try {
      const message = await resetPasswordRequest({
        token,
        email,
        password: data.password,
        password_confirmation: data.confirmPassword,
      })
      toast.success(message)
      navigate({ to: '/sign-in', replace: true })
    } catch (error) {
      toast.error(
        applyValidationErrors(
          error,
          (field, err) => {
            if (field === 'password' || field === 'confirmPassword') {
              form.setError(field, err)
            }
          },
          { password_confirmation: 'confirmPassword' }
        )
      )
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className={cn('grid gap-3', className)}
        {...props}
      >
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem>
              <FormLabel>New password</FormLabel>
              <FormControl>
                <PasswordInput placeholder='********' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='confirmPassword'
          render={({ field }) => (
            <FormItem>
              <FormLabel>Confirm password</FormLabel>
              <FormControl>
                <PasswordInput placeholder='********' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button className='mt-2' disabled={isLoading || !token || !email}>
          {isLoading ? <Loader2 className='animate-spin' /> : null}
          Reset password
        </Button>
      </form>
    </Form>
  )
}
