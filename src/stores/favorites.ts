import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { addFavorite, getFavorites, removeFavorite } from '@/services/favorites'
import type { Product } from '@/types'

export const useFavoritesStore = defineStore('favorites', () => {
  const products = ref<Product[]>([])
  const productIds = ref<Set<string>>(new Set())
  const loading = ref(false)
  const error = ref('')
  const loadedForUser = ref<string | null>(null)
  const count = computed(() => productIds.value.size)

  function has(productId: string) {
    return productIds.value.has(productId)
  }

  async function fetchAll(userId: string, force = false) {
    if (!force && loadedForUser.value === userId) return
    loading.value = true
    error.value = ''
    try {
      const rows = await getFavorites()
      products.value = rows.flatMap((row) => (row.product ? [row.product] : []))
      productIds.value = new Set(rows.map((row) => row.product_id))
      loadedForUser.value = userId
    } catch (reason) {
      error.value = reason instanceof Error ? reason.message : 'Could not load favorites'
      throw reason
    } finally {
      loading.value = false
    }
  }

  async function toggle(userId: string, product: Product) {
    const wasFavorite = has(product.id)
    productIds.value = new Set(productIds.value)
    if (wasFavorite) {
      productIds.value.delete(product.id)
      products.value = products.value.filter((item) => item.id !== product.id)
    } else {
      productIds.value.add(product.id)
      products.value.unshift(product)
    }

    try {
      if (wasFavorite) await removeFavorite(userId, product.id)
      else await addFavorite(userId, product.id)
    } catch (reason) {
      if (wasFavorite) {
        productIds.value.add(product.id)
        products.value.unshift(product)
      } else {
        productIds.value.delete(product.id)
        products.value = products.value.filter((item) => item.id !== product.id)
      }
      productIds.value = new Set(productIds.value)
      throw reason
    }
  }

  function reset() {
    products.value = []
    productIds.value = new Set()
    loadedForUser.value = null
    error.value = ''
  }

  return { products, productIds, loading, error, count, has, fetchAll, toggle, reset }
})
