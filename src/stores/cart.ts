import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import type { CartItem, Product } from '@/types'
const KEY = 'nord-cart-v1'
export const useCartStore = defineStore('cart', () => {
  const read = (): CartItem[] => {
    try {
      return JSON.parse(localStorage.getItem(KEY) ?? '[]') as CartItem[]
    } catch {
      return []
    }
  }
  const items = ref<CartItem[]>(typeof window === 'undefined' ? [] : read())
  watch(items, (v) => localStorage.setItem(KEY, JSON.stringify(v)), { deep: true })
  const count = computed(() => items.value.reduce((s, i) => s + i.quantity, 0))
  const subtotal = computed(() => items.value.reduce((s, i) => s + i.product.price * i.quantity, 0))
  const total = subtotal
  function add(product: Product, quantity = 1) {
    const item = items.value.find((i) => i.product.id === product.id)
    if (item) item.quantity = Math.min(product.stock, item.quantity + quantity)
    else if (product.stock > 0)
      items.value.push({ product, quantity: Math.min(product.stock, quantity) })
  }
  function remove(id: string) {
    items.value = items.value.filter((i) => i.product.id !== id)
  }
  function setQuantity(id: string, q: number) {
    const item = items.value.find((i) => i.product.id === id)
    if (!item) return
    item.quantity = Math.max(1, Math.min(item.product.stock, q))
  }
  const increment = (id: string) => {
    const i = items.value.find((x) => x.product.id === id)
    if (i) setQuantity(id, i.quantity + 1)
  }
  const decrement = (id: string) => {
    const i = items.value.find((x) => x.product.id === id)
    if (i) i.quantity <= 1 ? remove(id) : setQuantity(id, i.quantity - 1)
  }
  const clear = () => (items.value = [])
  return { items, count, subtotal, total, add, remove, setQuantity, increment, decrement, clear }
})
