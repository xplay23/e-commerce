import { api } from '@/lib/api'
import type { Product } from '@/types'

export type FavoriteRow = { product_id: string; product: Product | null }

export function getFavorites(): Promise<FavoriteRow[]> {
  return api<FavoriteRow[]>('favorites')
}

export async function addFavorite(_userId: string, productId: string): Promise<void> {
  await api('favorites', { method: 'POST', body: JSON.stringify({ product_id: productId }) })
}

export async function removeFavorite(_userId: string, productId: string): Promise<void> {
  await api(`favorites?product_id=${encodeURIComponent(productId)}`, { method: 'DELETE' })
}
