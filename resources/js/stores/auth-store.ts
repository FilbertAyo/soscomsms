import { create } from 'zustand'
import { type AuthUser } from '@/types/auth'

interface AuthState {
  user: AuthUser | null
  setUser: (user: AuthUser | null) => void
  reset: () => void
}

export const useAuthStore = create<AuthState>()((set) => ({
  user: null,
  setUser: (user) => set({ user }),
  reset: () => set({ user: null }),
}))
