<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore(),
  register = computed(() => route.name === 'register'),
  name = ref(''),
  email = ref(''),
  password = ref(''),
  confirm = ref(''),
  error = ref(''),
  message = ref(''),
  busy = ref(false)
watch(register, () => {
  error.value = ''
  message.value = ''
  password.value = ''
  confirm.value = ''
})
async function submit() {
  if (busy.value) return
  error.value = ''
  message.value = ''
  if (register.value && password.value !== confirm.value) {
    error.value = 'Passwords do not match'
    return
  }
  busy.value = true
  try {
    if (register.value) {
      message.value = (await auth.register(name.value, email.value, password.value)).message
      password.value = ''
      confirm.value = ''
    } else {
      const result = await auth.login(email.value, password.value)
      password.value = ''
      if (result.requires_email)
        message.value = result.message || 'Check your email to confirm sign-in.'
      else {
        const target = typeof route.query.redirect === 'string' ? route.query.redirect : '/account'
        await router.push(target.startsWith('/') && !target.startsWith('//') ? target : '/account')
      }
    }
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
      <h1>{{ register ? 'Create account' : 'Welcome back' }}</h1>
      <p>
        {{
          register
            ? 'Keep your favorites and follow your orders.'
            : 'Enter your details to continue.'
        }}
      </p>
      <form @submit.prevent="submit">
        <BaseInput
          v-if="register"
          v-model="name"
          label="Name"
          autocomplete="name"
          required
          maxlength="120"
        /><BaseInput
          v-model="email"
          label="Email"
          type="email"
          autocomplete="email"
          required
          maxlength="190"
        /><BaseInput
          v-model="password"
          label="Password"
          type="password"
          :autocomplete="register ? 'new-password' : 'current-password'"
          required
          :minlength="register ? 8 : undefined"
          :maxlength="register ? 128 : 1024"
        /><BaseInput
          v-if="register"
          v-model="confirm"
          label="Confirm password"
          type="password"
          autocomplete="new-password"
          required
          minlength="8"
          maxlength="128"
        />
        <small v-if="register">Use 8–128 characters. A long, unique passphrase works well.</small>
        <small v-else
          >After 3 incorrect passwords, sign-in is blocked until you reset your password by
          email.</small
        >
        <p v-if="error" class="alert" role="alert">{{ error }}</p>
        <p v-if="message" role="status">{{ message }}</p>
        <BaseButton type="submit" :loading="busy" :disabled="busy">{{
          register ? 'Create account' : 'Sign in'
        }}</BaseButton>
      </form>
      <p>
        {{ register ? 'Already a member?' : 'New to NORD?' }}
        <RouterLink :to="register ? '/login' : '/register'">{{
          register ? 'Sign in' : 'Create account'
        }}</RouterLink>
      </p>
      <p v-if="!register"><RouterLink to="/forgot-password">Reset password</RouterLink></p>
      <p><RouterLink to="/resend-verification">Resend verification email</RouterLink></p>
    </div>
  </div>
</template>
