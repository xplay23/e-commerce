<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useCartStore } from '@/stores/cart'
import { api } from '@/lib/api'
const cart = useCartStore(),
  router = useRouter(),
  form = reactive({ name: '', email: '', phone: '', address: '', city: '', comment: '' }),
  error = ref(''),
  busy = ref(false)
async function submit() {
  if (!form.name || !form.email || !form.phone || !form.address || !form.city) {
    error.value = 'Please complete all required fields.'
    return
  }
  busy.value = true
  try {
    const { id } = await api<{ id: string }>('orders', {
      method: 'POST',
      body: JSON.stringify({
        customer_name: form.name,
        customer_email: form.email,
        customer_phone: form.phone,
        address: form.address,
        city: form.city,
        comment: form.comment,
        items: cart.items.map((item) => ({
          product_id: item.product.id,
          quantity: item.quantity,
        })),
      }),
    })
    cart.clear()
    await router.push(`/order-success/${id}`)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Order could not be placed'
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <div class="container checkout">
    <section>
      <p class="eyebrow">Checkout</p>
      <h1>Where should<br />we send it?</h1>
      <form @submit.prevent="submit">
        <BaseInput v-model="form.name" label="Full name" />
        <div class="form-row">
          <BaseInput v-model="form.email" label="Email" type="email" /><BaseInput
            v-model="form.phone"
            label="Phone"
          />
        </div>
        <BaseInput v-model="form.address" label="Address" /><BaseInput
          v-model="form.city"
          label="City"
        /><label class="field"
          ><span>Comment (optional)</span><textarea v-model="form.comment" rows="3" />
        </label>
        <p v-if="error" class="alert">{{ error }}</p>
        <BaseButton type="submit" :loading="busy" :disabled="!cart.items.length"
          >Place order</BaseButton
        >
      </form>
    </section>
    <aside class="summary">
      <h2>Your order</h2>
      <p v-for="i in cart.items" :key="i.product.id">
        <span>{{ i.quantity }} × {{ i.product.name }}</span
        ><b>${{ i.product.price * i.quantity }}</b>
      </p>
      <hr />
      <p class="total">
        <span>Total</span><b>${{ cart.total }}</b>
      </p>
    </aside>
  </div>
</template>
