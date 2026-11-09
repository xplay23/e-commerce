<script setup lang="ts">
defineProps<{ kind?: 'success' }>()
</script>
<template>
  <div class="container simple-page">
    <p class="eyebrow">NORD Objects</p>
    <h1 v-if="kind === 'success'">Order received.</h1>
    <h1 v-else>Objects with intention.</h1>
    <p v-if="kind === 'success'">
      Thank you. Your order reference is <b>{{ $route.params.id }}</b
      >. Please keep this reference for your records.
    </p>
    <p v-else>
      We work with thoughtful makers to bring useful, enduring objects into everyday life. Fewer
      things. Better chosen.
    </p>
    <RouterLink class="text-link" to="/catalog">Explore the collection →</RouterLink>
  </div>
</template>
