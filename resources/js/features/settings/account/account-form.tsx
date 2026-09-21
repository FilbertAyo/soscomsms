import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyValidationErrors, meQueryKey, updateProfileRequest } from '@/lib/auth'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'

const accountFormSchema = z.object({
  name: z
    .string()
    .min(1, 'Please enter your name.')
    .min(2, 'Name must be at least 2 characters.')
    .max(255, 'Name must not be longer than 255 characters.'),
  email: z.email('Please enter a valid email.'),
})

type AccountFormValues = z.infer<typeof accountFormSchema>

export function AccountForm() {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)

  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountFormSchema),
    defaultValues: {
      name: user?.name ?? '',
      email: user?.email ?? '',
    },
  })

  async function onSubmit(data: AccountFormValues) {
    try {
      const updated = await updateProfileRequest({
        name: data.name,
        email: data.email,
      })
      useAuthStore.getState().setUser(updated)
      queryClient.setQueryData(meQueryKey, updated)
      toast.success('Account saved.')
    } catch (error) {
      toast.error(
        applyValidationErrors(error, (field, err) => {
          if (field === 'name' || field === 'email') {
            form.setError(field, err)
          }
        })
      )
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-8'>
        <FormField
          control={form.control}
          name='name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>Name</FormLabel>
              <FormControl>
                <Input placeholder='Your name' {...field} />
              </FormControl>
              <FormDescription>
                This is the name that will be displayed on your profile and in
                emails.
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='email'
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input type='email' placeholder='you@example.com' {...field} />
              </FormControl>
              <FormDescription>
                Used to sign in and to send password reset links.
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type='submit' disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting ? 'Saving…' : 'Update account'}
        </Button>
      </form>
    </Form>
  )
}
