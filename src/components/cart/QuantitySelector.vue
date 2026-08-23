<script setup lang="ts">
defineProps<{ modelValue: number; max: number }>()
defineEmits<{ 'update:modelValue': [number] }>()
</script>
<template>
  <div class="qty">
    <button :disabled="modelValue <= 1" @click="$emit('update:modelValue', modelValue - 1)">
      −</button
    ><span>{{ modelValue }}</span
    ><button :disabled="modelValue >= max" @click="$emit('update:modelValue', modelValue + 1)">
      +
    </button>
  </div>
</template>
