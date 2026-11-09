import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { AuthUser, Profile } from '@/types'

type AuthResponse = { user?: AuthUser; requires_email?: boolean; message?: string }

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const profile = ref<Profile | null>(null)

  function setUser(value: AuthUser | null) {
    user.value = value
    profile.value = value
  }

  async function init() {
    // Remove legacy bearer tokens; authentication now uses HttpOnly cookies.
    try {
      localStorage.removeItem('nord-auth-token')
    } catch {
      /* Storage may be disabled. */
    }
    window.addEventListener('nord:session-expired', () => setUser(null))
    try {
      setUser(await api<AuthUser>('auth/me'))
    } catch {
      setUser(null)
    }
  }

  async function login(email: string, password: string) {
    const result = await api<AuthResponse>('auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    if (result.user) setUser(result.user)
    return result
  }

  async function register(name: string, email: string, password: string) {
    return api<{ message: string }>('auth/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password }),
    })
  }

  async function logout() {
    try {
      await api('auth/logout', { method: 'POST' })
    } finally {
      setUser(null)
    }
  }

  async function confirmLogin(token: string) {
    const result = await api<AuthResponse>('auth/confirm-login', {
      method: 'POST',
      body: JSON.stringify({ token }),
    })
    if (result.user) setUser(result.user)
  }

  return { user, profile, init, login, register, logout, confirmLogin }
})
