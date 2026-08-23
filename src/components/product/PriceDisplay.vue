<script setup lang="ts">
import { formatPrice, discountPercent } from '@/utils/format'
defineProps<{ price: number; oldPrice: number | null }>()
</script>
<template>
  <div class="price">
    <strong>{{ formatPrice(price) }}</strong
    ><del v-if="oldPrice">{{ formatPrice(oldPrice) }}</del
    ><span v-if="discountPercent(price, oldPrice)">−{{ discountPercent(price, oldPrice) }}%</span>
  </div>
</template>
