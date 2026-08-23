<script setup lang="ts">
import { onMounted } from 'vue'
import ProductGrid from '@/components/product/ProductGrid.vue'
import BaseEmptyState from '@/components/ui/BaseEmptyState.vue'
import { useAuthStore } from '@/stores/auth'
import { useFavoritesStore } from '@/stores/favorites'

const auth = useAuthStore()
const favorites = useFavoritesStore()

onMounted(async () => {
  if (auth.user) {
    try {
      await favorites.fetchAll(auth.user.id, true)
    } catch {
      /* Render store error. */
    }
  }
})
</script>

<template>
  <div class="container narrow-page">
    <p class="eyebrow">Your collection</p>
    <h1>
      Saved objects <sup>{{ favorites.count }}</sup>
    </h1>
    <p v-if="favorites.error" class="alert">{{ favorites.error }}</p>
    <ProductGrid :products="favorites.products" :loading="favorites.loading" />
    <BaseEmptyState
      v-if="!favorites.loading && !favorites.error && !favorites.products.length"
      title="Nothing saved yet"
      text="Use the heart on any object to keep it here."
    >
      <RouterLink class="button button--dark" to="/catalog">Explore objects</RouterLink>
    </BaseEmptyState>
  </div>
</template>
