import { api } from '@/lib/api'
import type { Product } from '@/types'

export type ProductPayload = {
  category_id: string
  name: string
  slug: string
  description: string
  short_description: string
  price: number
  old_price: number | null
  stock: number
  image_url: string
  is_active: boolean
  is_featured: boolean
}

export function getAdminProducts(): Promise<Product[]> {
  return api<Product[]>('admin/products')
}

export function createProduct(payload: ProductPayload): Promise<{ id: string }> {
  return api('admin/products', { method: 'POST', body: JSON.stringify(payload) })
}

export async function updateProduct(id: string, payload: ProductPayload): Promise<void> {
  await api(`admin/products/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}

export async function deleteProduct(id: string): Promise<void> {
  await api(`admin/products/${id}`, { method: 'DELETE' })
}
