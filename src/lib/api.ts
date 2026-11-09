const defaultApiUrl = '/api'
const API_URL = (import.meta.env.VITE_API_URL || defaultApiUrl).replace(/\/$/, '')
let csrfToken: string | null = null
let csrfRequest: Promise<string> | null = null

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message)
  }
}

async function getCsrfToken(): Promise<string> {
  if (csrfToken) return csrfToken
  if (!csrfRequest) {
    csrfRequest = (async () => {
      const response = await fetch(`${API_URL}/auth/csrf`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      })
      const result = (await response.json()) as { csrf_token?: string }
      if (!response.ok || !result.csrf_token)
        throw new ApiError('Could not secure this request. Reload the page.', response.status)
      csrfToken = result.csrf_token
      return csrfToken
    })().finally(() => {
      csrfRequest = null
    })
  }
  return csrfRequest
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  const method = (options.method || 'GET').toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers.set('Content-Type', 'application/json')
    headers.set('X-CSRF-Token', await getCsrfToken())
  }

  let response: Response
  try {
    response = await fetch(`${API_URL}/${path.replace(/^\//, '')}`, {
      ...options,
      method,
      headers,
      credentials: 'same-origin',
    })
  } catch {
    throw new ApiError('API server is unavailable', 0)
  }

  const payload = (await response.json().catch(() => ({}))) as {
    message?: string
    csrf_token?: string
  }
  if (payload.csrf_token) csrfToken = payload.csrf_token
  if (!response.ok) {
    if (response.status === 403) csrfToken = null
    if (response.status === 401 && !path.startsWith('auth/login')) {
      csrfToken = null
      window.dispatchEvent(new Event('nord:session-expired'))
    }
    throw new ApiError(payload.message || 'Request failed', response.status)
  }
  return payload as T
}
