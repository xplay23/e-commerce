<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'dark' | 'light' | 'ghost'
    loading?: boolean
    disabled?: boolean
    type?: 'button' | 'submit'
  }>(),
  { variant: 'dark', type: 'button' },
)
defineEmits<{ click: [MouseEvent] }>()
</script>
<template>
  <button
    class="button"
    :class="`button--${variant}`"
    :type
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="spinner" /><slot v-else />
  </button>
</template>
