<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getOrders } from '@/services/orders'
import { formatDate, formatPrice } from '@/utils/format'
import type { Order } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const orders = ref<Order[]>([])
const error = ref('')

async function logout() {
  await auth.logout()
  await router.push('/')
}

onMounted(async () => {
  try {
    orders.value = await getOrders()
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Could not load orders'
  }
})
</script>

<template>
  <div class="container narrow-page">
    <p class="eyebrow">Private space</p>
    <h1>My account</h1>
    <div class="account-grid">
      <section>
        <h2>Profile</h2>
        <p>
          <b>{{ auth.profile?.name || 'NORD member' }}</b
          ><br />{{ auth.user?.email }}<br />{{ auth.profile?.phone || 'No phone added' }}
        </p>
        <button class="text-link" @click="logout">Sign out →</button>
      </section>
      <section>
        <h2>Orders</h2>
        <p v-if="error" class="alert">{{ error }}</p>
        <p v-else-if="!orders.length">
          Your order history will appear here after your first purchase.
        </p>
        <div v-else class="orders-list">
          <p v-for="order in orders" :key="order.id">
            <span>#{{ order.id }} · {{ formatDate(order.created_at) }}</span
            ><b>{{ formatPrice(Number(order.total)) }} · {{ order.status }}</b>
          </p>
        </div>
      </section>
      <RouterLink class="account-tile" to="/account/favorites"
        ><span>Saved objects</span><b>View favorites →</b></RouterLink
      >
      <RouterLink v-if="auth.profile?.role === 'admin'" class="account-tile" to="/admin"
        ><span>Administration</span><b>Manage products →</b></RouterLink
      >
    </div>
  </div>
</template>
