<script setup lang="ts">
defineProps<{ title: string; text: string }>()
</script>
<template>
  <div class="empty">
    <div class="empty__mark">N</div>
    <h2>{{ title }}</h2>
    <p>{{ text }}</p>
    <slot />
  </div>
</template>
