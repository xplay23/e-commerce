const defaultApiUrl = import.meta.env.DEV
  ? 'http://localhost:8080/api'
  : `${window.location.origin}/e-commerce-vue/backend/public/api`
const API_URL = (import.meta.env.VITE_API_URL || defaultApiUrl).replace(/\/$/, '')
const TOKEN_KEY = 'nord-auth-token'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message)
  }
}

export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body) headers.set('Content-Type', 'application/json')
  const token = tokenStorage.get()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  let response: Response
  try {
    response = await fetch(`${API_URL}/${path.replace(/^\//, '')}`, { ...options, headers })
  } catch {
    throw new ApiError('API server is unavailable', 0)
  }

  const payload = (await response.json().catch(() => ({}))) as { message?: string }
  if (!response.ok) {
    if (response.status === 401) tokenStorage.clear()
    throw new ApiError(payload.message || 'Request failed', response.status)
  }
  return payload as T
}
