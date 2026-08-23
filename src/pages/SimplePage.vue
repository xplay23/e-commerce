<script setup lang="ts">
defineProps<{ kind?: string }>()
</script>
<template>
  <div class="container simple-page">
    <p class="eyebrow">NORD Objects</p>
    <h1 v-if="kind === 'success'">Order received.</h1>
    <h1 v-else-if="kind === 'favorites'">Saved objects</h1>
    <h1 v-else-if="kind === 'admin'">Studio administration</h1>
    <h1 v-else>Objects with intention.</h1>
    <p v-if="kind === 'success'">
      Thank you. Your order reference is <b>{{ $route.params.id }}</b
      >. We’ll send the next steps by email.
    </p>
    <p v-else-if="kind === 'favorites'">
      Your saved pieces will live here. Connect your Supabase project and sign in to keep them
      across devices.
    </p>
    <p v-else-if="kind === 'admin'">
      Manage products, categories, and order status from this protected workspace.
    </p>
    <p v-else>
      We work with thoughtful makers to bring useful, enduring objects into everyday life. Fewer
      things. Better chosen.
    </p>
    <RouterLink class="text-link" to="/catalog">Explore the collection →</RouterLink>
  </div>
</template>
