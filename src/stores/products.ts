import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { getCategories, getProducts, PAGE_SIZE } from '@/services/products'
import type { CatalogFilters, Category, Product } from '@/types'
export const useProductsStore = defineStore('products', () => {
  const items = ref<Product[]>([]),
    categories = ref<Category[]>([]),
    loading = ref(false),
    error = ref(''),
    count = ref(0)
  const filters = ref<CatalogFilters>({
    category: '',
    search: '',
    minPrice: null,
    maxPrice: null,
    inStock: false,
    sort: 'newest',
    page: 1,
  })
  const pages = computed(() => Math.max(1, Math.ceil(count.value / PAGE_SIZE)))
  async function fetchAll() {
    loading.value = true
    error.value = ''
    try {
      const [result, cats] = await Promise.all([
        getProducts(filters.value),
        categories.value.length ? Promise.resolve(categories.value) : getCategories(),
      ])
      items.value = result.items
      count.value = result.count
      categories.value = cats
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Could not load products'
    } finally {
      loading.value = false
    }
  }
  return { items, categories, loading, error, count, filters, pages, fetchAll }
})
