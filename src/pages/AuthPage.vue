<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'
import { isSupabaseConfigured } from '@/lib/supabase'
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore(),
  register = computed(() => route.name === 'register'),
  name = ref(''),
  email = ref(''),
  password = ref(''),
  confirm = ref(''),
  error = ref(''),
  busy = ref(false)
async function submit() {
  error.value = ''
  if (register.value && password.value !== confirm.value) {
    error.value = 'Passwords do not match'
    return
  }
  if (!isSupabaseConfigured) {
    error.value = 'Connect Supabase in .env.local to use authentication.'
    return
  }
  busy.value = true
  try {
    register.value
      ? await auth.register(name.value, email.value, password.value)
      : await auth.login(email.value, password.value)
    await router.push(String(route.query.redirect || '/account'))
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Authentication failed'
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div class="auth-page">
    <div class="auth-card">
      <p class="eyebrow">NORD account</p>
      <h1>{{ register ? 'Create account' : 'Welcome back' }}</h1>
      <p>
        {{
          register
            ? 'Keep your favorites and follow your orders.'
            : 'Enter your details to continue.'
        }}
      </p>
      <form @submit.prevent="submit">
        <BaseInput v-if="register" v-model="name" label="Name" /><BaseInput
          v-model="email"
          label="Email"
          type="email"
        /><BaseInput v-model="password" label="Password" type="password" /><BaseInput
          v-if="register"
          v-model="confirm"
          label="Confirm password"
          type="password"
        />
        <p v-if="error" class="alert">{{ error }}</p>
        <BaseButton type="submit" :loading="busy">{{
          register ? 'Create account' : 'Sign in'
        }}</BaseButton>
      </form>
      <p>
        {{ register ? 'Already a member?' : 'New to NORD?' }}
        <RouterLink :to="register ? '/login' : '/register'">{{
          register ? 'Sign in' : 'Create account'
        }}</RouterLink>
      </p>
    </div>
  </div>
</template>
