<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, SlidersHorizontal } from 'lucide-vue-next'
import { useProductsStore } from '@/stores/products'
import ProductGrid from '@/components/product/ProductGrid.vue'
import BaseEmptyState from '@/components/ui/BaseEmptyState.vue'
import BasePagination from '@/components/ui/BasePagination.vue'
import type { SortOption } from '@/types'
const store = useProductsStore(),
  route = useRoute(),
  router = useRouter(),
  showFilters = ref(false),
  searchDraft = ref('')
let timer = 0
function fromQuery() {
  store.filters = {
    category: String(route.query.category ?? ''),
    search: String(route.query.search ?? ''),
    minPrice: route.query.minPrice ? Number(route.query.minPrice) : null,
    maxPrice: route.query.maxPrice ? Number(route.query.maxPrice) : null,
    inStock: route.query.inStock === 'true',
    sort: (route.query.sort as SortOption) || 'newest',
    page: Number(route.query.page) || 1,
  }
  searchDraft.value = store.filters.search
}
function sync() {
  const f = store.filters
  void router.replace({
    query: {
      category: f.category || undefined,
      search: f.search || undefined,
      minPrice: f.minPrice ?? undefined,
      maxPrice: f.maxPrice ?? undefined,
      inStock: f.inStock ? 'true' : undefined,
      sort: f.sort === 'newest' ? undefined : f.sort,
      page: f.page > 1 ? f.page : undefined,
    },
  })
}
function changed() {
  store.filters.page = 1
  sync()
}
function search() {
  clearTimeout(timer)
  timer = window.setTimeout(() => {
    store.filters.search = searchDraft.value
    changed()
  }, 300)
}
function changePage(page: number) {
  store.filters.page = page
  sync()
}
watch(
  () => route.query,
  async () => {
    fromQuery()
    await store.fetchAll()
  },
  { deep: true },
)
onMounted(async () => {
  fromQuery()
  await store.fetchAll()
})
</script>
<template>
  <div class="catalog container">
    <div class="catalog__top">
      <div>
        <p class="eyebrow">Our collection</p>
        <h1>All objects</h1>
      </div>
      <p>{{ store.count }} considered pieces for work, rest, and everything between.</p>
    </div>
    <div class="catalog__toolbar">
      <label class="search"
        ><Search :size="18" /><input
          v-model="searchDraft"
          placeholder="Search objects"
          @input="search" /></label
      ><button class="filter-toggle" @click="showFilters = !showFilters">
        <SlidersHorizontal :size="18" /> Filters</button
      ><select v-model="store.filters.sort" @change="changed">
        <option value="newest">Newest</option>
        <option value="price_asc">Price: low to high</option>
        <option value="price_desc">Price: high to low</option>
        <option value="name_asc">Name A–Z</option>
        <option value="name_desc">Name Z–A</option>
      </select>
    </div>
    <div class="catalog__body">
      <aside :class="{ open: showFilters }">
        <div class="filter-group">
          <h3>Category</h3>
          <label
            ><input v-model="store.filters.category" type="radio" value="" @change="changed" /> All
            objects</label
          ><label v-for="c in store.categories" :key="c.id"
            ><input
              v-model="store.filters.category"
              type="radio"
              :value="c.slug"
              @change="changed"
            />
            {{ c.name }}</label
          >
        </div>
        <div class="filter-group">
          <h3>Price range</h3>
          <div class="price-inputs">
            <input
              v-model.number="store.filters.minPrice"
              type="number"
              min="0"
              placeholder="Min"
              @change="changed"
            /><input
              v-model.number="store.filters.maxPrice"
              type="number"
              min="0"
              placeholder="Max"
              @change="changed"
            />
          </div>
        </div>
        <label class="stock"
          ><input v-model="store.filters.inStock" type="checkbox" @change="changed" /> In stock
          only</label
        >
      </aside>
      <div class="catalog__results">
        <p v-if="store.error" class="alert">{{ store.error }}</p>
        <ProductGrid :products="store.items" :loading="store.loading" /><BaseEmptyState
          v-if="!store.loading && !store.error && !store.items.length"
          title="Nothing found"
          text="Try removing a filter or searching for something else."
        /><BasePagination :page="store.filters.page" :pages="store.pages" @change="changePage" />
      </div>
    </div>
  </div>
</template>
