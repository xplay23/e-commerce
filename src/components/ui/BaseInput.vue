<script setup lang="ts">
defineProps<{
  modelValue: string | number
  label?: string
  placeholder?: string
  type?: string
  error?: string
  min?: number
}>()
defineEmits<{ 'update:modelValue': [string] }>()
</script>
<template>
  <label class="field"
    ><span v-if="label">{{ label }}</span
    ><input
      :value="modelValue"
      :type="type || 'text'"
      :placeholder
      :min
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    /><small v-if="error">{{ error }}</small></label
  >
</template>
