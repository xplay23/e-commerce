export type Product = {
  id: string
  category_id: string | null
  name: string
  slug: string
  description: string
  short_description: string | null
  price: number
  old_price: number | null
  stock: number
  image_url: string | null
  is_active: boolean
  is_featured: boolean
  created_at: string
  updated_at: string
  category?: Category | null
  images?: ProductImage[]
}
export type Category = {
  id: string
  name: string
  slug: string
  description: string | null
  image_url: string | null
  created_at: string
}
export type ProductImage = {
  id: string
  product_id: string
  url: string
  sort_order: number
  created_at: string
}
export type Profile = {
  id: string
  name: string | null
  phone: string | null
  role: 'customer' | 'admin'
  created_at: string
}
export type OrderStatus = 'pending' | 'processing' | 'shipped' | 'completed' | 'cancelled'
export type Order = {
  id: string
  user_id: string | null
  customer_name: string
  customer_email: string
  customer_phone: string
  address: string
  city: string
  comment: string | null
  status: OrderStatus
  subtotal: number
  total: number
  created_at: string
  updated_at: string
  items?: OrderItem[]
}
export type OrderItem = {
  id: string
  order_id: string
  product_id: string | null
  product_name: string
  price: number
  quantity: number
  created_at: string
}
export type CartItem = { product: Product; quantity: number }
export type SortOption = 'newest' | 'price_asc' | 'price_desc' | 'name_asc' | 'name_desc'
export type CatalogFilters = {
  category: string
  search: string
  minPrice: number | null
  maxPrice: number | null
  inStock: boolean
  sort: SortOption
  page: number
}
