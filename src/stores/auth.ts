import { ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError, api, tokenStorage } from '@/lib/api'
import type { AuthUser, Profile } from '@/types'

type AuthResponse = { token: string; user: AuthUser }

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const profile = ref<Profile | null>(null)
  const loading = ref(true)

  function setUser(value: AuthUser | null) {
    user.value = value
    profile.value = value
  }

  async function init() {
    if (!tokenStorage.get()) {
      loading.value = false
      return
    }
    try {
      setUser(await api<AuthUser>('auth/me'))
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) tokenStorage.clear()
    } finally {
      loading.value = false
    }
  }

  async function login(email: string, password: string) {
    const result = await api<AuthResponse>('auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    tokenStorage.set(result.token)
    setUser(result.user)
  }

  async function register(name: string, email: string, password: string) {
    const result = await api<AuthResponse>('auth/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password }),
    })
    tokenStorage.set(result.token)
    setUser(result.user)
  }

  async function logout() {
    try {
      await api('auth/logout', { method: 'POST' })
    } finally {
      tokenStorage.clear()
      setUser(null)
    }
  }

  return { user, profile, loading, init, login, register, logout }
})
