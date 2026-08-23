import { isSupabaseConfigured, supabase } from '@/lib/supabase'
import { demoCategories, demoProducts } from '@/data/demo'
import type { CatalogFilters, Category, Product } from '@/types'
export const PAGE_SIZE = 12
export async function getCategories(): Promise<Category[]> {
  if (!isSupabaseConfigured) return demoCategories
  const { data, error } = await supabase.from('categories').select('*').order('name')
  if (error) throw error
  return data as unknown as Category[]
}
export async function getFeaturedProducts(limit = 4): Promise<Product[]> {
  if (!isSupabaseConfigured)
    return demoProducts
      .filter((product) => product.is_featured && product.is_active)
      .slice(0, limit)
  const { data, error } = await supabase
    .from('products')
    .select('*, category:categories(*), images:product_images(*)')
    .eq('is_active', true)
    .eq('is_featured', true)
    .order('created_at', { ascending: false })
    .limit(limit)
  if (error) throw error
  return data as unknown as Product[]
}
export async function getProducts(
  filters: CatalogFilters,
): Promise<{ items: Product[]; count: number }> {
  if (!isSupabaseConfigured) {
    let rows = [...demoProducts]
    if (filters.category) rows = rows.filter((p) => p.category?.slug === filters.category)
    if (filters.search)
      rows = rows.filter((p) =>
        (p.name + ' ' + p.description).toLowerCase().includes(filters.search.toLowerCase()),
      )
    if (filters.minPrice !== null) rows = rows.filter((p) => p.price >= filters.minPrice!)
    if (filters.maxPrice !== null) rows = rows.filter((p) => p.price <= filters.maxPrice!)
    if (filters.inStock) rows = rows.filter((p) => p.stock > 0)
    rows.sort((a, b) =>
      filters.sort === 'price_asc'
        ? a.price - b.price
        : filters.sort === 'price_desc'
          ? b.price - a.price
          : filters.sort === 'name_asc'
            ? a.name.localeCompare(b.name)
            : filters.sort === 'name_desc'
              ? b.name.localeCompare(a.name)
              : b.created_at.localeCompare(a.created_at),
    )
    const count = rows.length,
      start = (filters.page - 1) * PAGE_SIZE
    return { items: rows.slice(start, start + PAGE_SIZE), count }
  }
  let query = supabase
    .from('products')
    .select('*, category:categories(*), images:product_images(*)', { count: 'exact' })
    .eq('is_active', true)
  if (filters.category) query = query.eq('categories.slug', filters.category)
  if (filters.search) query = query.ilike('name', `%${filters.search}%`)
  if (filters.minPrice !== null) query = query.gte('price', filters.minPrice)
  if (filters.maxPrice !== null) query = query.lte('price', filters.maxPrice)
  if (filters.inStock) query = query.gt('stock', 0)
  const [column, ascending] =
    filters.sort === 'price_asc'
      ? ['price', true]
      : filters.sort === 'price_desc'
        ? ['price', false]
        : filters.sort === 'name_asc'
          ? ['name', true]
          : filters.sort === 'name_desc'
            ? ['name', false]
            : ['created_at', false]
  const from = (filters.page - 1) * PAGE_SIZE
  const { data, error, count } = await query
    .order(column, { ascending })
    .range(from, from + PAGE_SIZE - 1)
  if (error) throw error
  return { items: data as unknown as Product[], count: count ?? 0 }
}
export async function getProduct(slug: string): Promise<Product | null> {
  if (!isSupabaseConfigured) return demoProducts.find((p) => p.slug === slug) ?? null
  const { data, error } = await supabase
    .from('products')
    .select('*, category:categories(*), images:product_images(*)')
    .eq('slug', slug)
    .eq('is_active', true)
    .maybeSingle()
  if (error) throw error
  return data as unknown as Product | null
}
