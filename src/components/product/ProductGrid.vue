<script setup lang="ts">
import type { Product } from '@/types'
import ProductCard from './ProductCard.vue'
defineProps<{ products: Product[]; loading?: boolean }>()
</script>
<template>
  <div class="product-grid">
    <template v-if="loading"
      ><div v-for="n in 8" :key="n" class="skeleton-card"><i /><span /><span /></div></template
    ><ProductCard v-else v-for="p in products" :key="p.id" :product="p" />
  </div>
</template>
