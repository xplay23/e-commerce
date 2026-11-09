<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore()
const token = ref(''),
  email = ref(''),
  password = ref(''),
  confirm = ref('')
const error = ref(''),
  message = ref(''),
  busy = ref(false),
  done = ref(false)
const mode = computed(() => String(route.name))
const needsEmail = computed(() => ['forgot-password', 'resend-verification'].includes(mode.value))
const title = computed(
  () =>
    ({
      'forgot-password': 'Reset password',
      'reset-password': 'Choose a new password',
      'resend-verification': 'Verify your email',
      'verify-email': 'Confirm your email',
      'confirm-login': 'Confirm administrator sign-in',
    })[mode.value] || 'Account security',
)
const button = computed(
  () =>
    ({
      'forgot-password': 'Send reset link',
      'reset-password': 'Save new password',
      'resend-verification': 'Send verification email',
      'verify-email': 'Verify email',
      'confirm-login': 'Confirm sign-in',
    })[mode.value] || 'Continue',
)

watch(
  () => route.name,
  () => {
    token.value = new URLSearchParams(route.hash.slice(1)).get('token') || ''
    error.value = ''
    message.value = ''
    done.value = false
    password.value = ''
    confirm.value = ''
    if (route.hash) void router.replace({ path: route.path, query: route.query, hash: '' })
  },
  { immediate: true },
)

async function submit() {
  if (busy.value || done.value) return
  error.value = ''
  if (mode.value === 'reset-password' && password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  busy.value = true
  try {
    if (mode.value === 'confirm-login') {
      await auth.confirmLogin(token.value)
      token.value = ''
      await router.replace('/admin')
    } else {
      const result = await api<{ message: string }>(`auth/${mode.value}`, {
        method: 'POST',
        body: JSON.stringify(
          needsEmail.value
            ? { email: email.value }
            : {
                token: token.value,
                ...(mode.value === 'reset-password' ? { password: password.value } : {}),
              },
        ),
      })
      message.value = result.message
      done.value = true
      token.value = ''
      password.value = ''
      confirm.value = ''
    }
  } catch (failure) {
    error.value =
      failure instanceof Error ? failure.message : 'Could not complete this request. Try again.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ title }}</h1>
      <p v-if="needsEmail">
        Enter your account email. If it matches an eligible account, we will send a single-use link.
      </p>
      <p v-else-if="mode === 'reset-password'">
        Choose a unique password with 8–128 characters. This will unlock your account and end all
        previous sessions.
      </p>
      <p v-else>Continue only if you requested this email. Never share the link with anyone.</p>
      <form v-if="!done && (needsEmail || token)" @submit.prevent="submit">
        <BaseInput
          v-if="needsEmail"
          v-model="email"
          label="Email"
          type="email"
          autocomplete="email"
          required
          maxlength="190"
        />
        <template v-if="mode === 'reset-password'">
          <BaseInput
            v-model="password"
            label="New password"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            maxlength="128"
          />
          <BaseInput
            v-model="confirm"
            label="Confirm new password"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            maxlength="128"
          />
        </template>
        <p v-if="error" class="alert" role="alert">{{ error }}</p>
        <BaseButton type="submit" :loading="busy" :disabled="busy">{{ button }}</BaseButton>
      </form>
      <p v-else-if="!done" class="alert" role="alert">
        This link is missing or has already been opened in this tab. Request a new email below.
      </p>
      <p v-if="message" role="status">{{ message }}</p>
      <p><RouterLink to="/login">Back to sign in</RouterLink></p>
      <p v-if="!needsEmail">
        <RouterLink :to="mode === 'verify-email' ? '/resend-verification' : '/forgot-password'"
          >Request a new email</RouterLink
        >
      </p>
    </div>
  </div>
</template>
