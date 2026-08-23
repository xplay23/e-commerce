import { supabase } from '@/lib/supabase'
import type { Product } from '@/types'

type FavoriteRow = { product_id: string; product: Product | null }

export async function getFavorites(): Promise<FavoriteRow[]> {
  const { data, error } = await supabase
    .from('favorites')
    .select('product_id, product:products(*, category:categories(*), images:product_images(*))')
    .order('created_at', { ascending: false })

  if (error) throw error
  return (data ?? []) as unknown as FavoriteRow[]
}

export async function addFavorite(userId: string, productId: string): Promise<void> {
  const { error } = await supabase.from('favorites').insert({ user_id: userId, product_id: productId } as never)
  if (error && error.code !== '23505') throw error
}

export async function removeFavorite(userId: string, productId: string): Promise<void> {
  const { error } = await supabase.from('favorites').delete().eq('user_id', userId).eq('product_id', productId)
  if (error) throw error
}
