<script setup lang="ts">
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import { useToastStore } from '@/stores/toast'
const toast = useToastStore()
</script>
<template>
  <AppHeader />
  <main><RouterView /></main>
  <AppFooter />
  <div class="toasts">
    <div v-for="item in toast.items" :key="item.id" :class="`toast toast--${item.kind}`">
      {{ item.message }}
    </div>
  </div>
</template>
