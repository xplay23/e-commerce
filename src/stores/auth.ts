import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { Session, User } from '@supabase/supabase-js'
import { isSupabaseConfigured, supabase } from '@/lib/supabase'
import type { Profile } from '@/types'
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null),
    session = ref<Session | null>(null),
    profile = ref<Profile | null>(null),
    loading = ref(true)
  async function fetchProfile() {
    if (!user.value) return
    const { data } = await supabase
      .from('profiles')
      .select('*')
      .eq('id', user.value.id)
      .maybeSingle()
    profile.value = data as unknown as Profile | null
  }
  async function init() {
    if (!isSupabaseConfigured) {
      loading.value = false
      return
    }
    const { data } = await supabase.auth.getSession()
    session.value = data.session
    user.value = data.session?.user ?? null
    if (user.value) await fetchProfile()
    supabase.auth.onAuthStateChange((_e, s) => {
      session.value = s
      user.value = s?.user ?? null
      void fetchProfile()
    })
    loading.value = false
  }
  async function login(email: string, password: string) {
    const { error } = await supabase.auth.signInWithPassword({ email, password })
    if (error) throw error
  }
  async function register(name: string, email: string, password: string) {
    const { error } = await supabase.auth.signUp({ email, password, options: { data: { name } } })
    if (error) throw error
  }
  async function logout() {
    await supabase.auth.signOut()
    profile.value = null
  }
  return { user, session, profile, loading, init, login, register, logout }
})
