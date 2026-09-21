import { z } from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { Otp } from '@/features/auth/otp'

const searchSchema = z.object({
  challenge: z.string().optional(),
  email: z.string().optional(),
  redirect: z.string().optional(),
})

export const Route = createFileRoute('/(auth)/otp')({
  validateSearch: searchSchema,
  component: Otp,
})
