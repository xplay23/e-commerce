import { ApiError, api } from '@/lib/api'
import type { CatalogFilters, Category, Product } from '@/types'

export const PAGE_SIZE = 12

export function getCategories(): Promise<Category[]> {
  return api<Category[]>('categories')
}

export async function getFeaturedProducts(limit = 4): Promise<Product[]> {
  const result = await api<{ items: Product[] }>(`products/featured?limit=${limit}`)
  return result.items
}

export function getProducts(filters: CatalogFilters): Promise<{ items: Product[]; count: number }> {
  const params = new URLSearchParams()
  if (filters.category) params.set('category', filters.category)
  if (filters.search) params.set('search', filters.search)
  if (filters.minPrice !== null) params.set('minPrice', String(filters.minPrice))
  if (filters.maxPrice !== null) params.set('maxPrice', String(filters.maxPrice))
  if (filters.inStock) params.set('inStock', 'true')
  params.set('sort', filters.sort)
  params.set('page', String(filters.page))
  return api<{ items: Product[]; count: number }>(`products?${params}`)
}

export async function getProduct(slug: string): Promise<Product | null> {
  try {
    return await api<Product>(`products/${encodeURIComponent(slug)}`)
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null
    throw error
  }
}
