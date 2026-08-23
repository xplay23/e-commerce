<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import { formatPrice } from '@/utils/format'
import QuantitySelector from '@/components/cart/QuantitySelector.vue'
import BaseEmptyState from '@/components/ui/BaseEmptyState.vue'
const cart = useCartStore()
</script>
<template>
  <div class="container narrow-page">
    <p class="eyebrow">Your selection</p>
    <h1>
      Shopping bag <sup>{{ cart.count }}</sup>
    </h1>
    <BaseEmptyState
      v-if="!cart.items.length"
      title="Your bag is empty"
      text="Take your time. Choose only what belongs."
      ><RouterLink class="button button--dark" to="/catalog"
        >Explore objects</RouterLink
      ></BaseEmptyState
    >
    <div v-else class="cart-layout">
      <div class="cart-list">
        <article v-for="item in cart.items" :key="item.product.id">
          <img :src="item.product.image_url || '/placeholder.svg'" />
          <div>
            <p class="eyebrow">{{ item.product.category?.name }}</p>
            <h3>{{ item.product.name }}</h3>
            <p>{{ formatPrice(item.product.price) }}</p>
            <QuantitySelector
              :model-value="item.quantity"
              :max="item.product.stock"
              @update:model-value="cart.setQuantity(item.product.id, $event)"
            />
          </div>
          <button class="icon-button" @click="cart.remove(item.product.id)"><X /></button>
        </article>
      </div>
      <aside class="summary">
        <h2>Summary</h2>
        <p>
          <span>Subtotal</span><b>{{ formatPrice(cart.subtotal) }}</b>
        </p>
        <p>
          <span>Shipping</span
          ><b>{{ cart.subtotal >= 200 ? 'Complimentary' : 'Calculated next' }}</b>
        </p>
        <hr />
        <p class="total">
          <span>Total</span><b>{{ formatPrice(cart.total) }}</b>
        </p>
        <RouterLink class="button button--dark" to="/checkout">Continue to checkout</RouterLink
        ><small>Taxes included where applicable.</small>
      </aside>
    </div>
  </div>
</template>
