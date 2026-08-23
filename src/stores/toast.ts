import { ref } from 'vue'
import { defineStore } from 'pinia'
export type Toast = { id: number; message: string; kind: 'success' | 'error' }
export const useToastStore = defineStore('toast', () => {
  const items = ref<Toast[]>([])
  function show(message: string, kind: Toast['kind'] = 'success') {
    const id = Date.now()
    items.value.push({ id, message, kind })
    setTimeout(() => (items.value = items.value.filter((x) => x.id !== id)), 3500)
  }
  return { items, show }
})
