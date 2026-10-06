import { api } from '@/lib/api'
import type { Order } from '@/types'

export function getOrders(): Promise<Order[]> {
  return api<Order[]>('orders')
}
