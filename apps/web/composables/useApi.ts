/**
 * Dunne client voor de KlimaatX-API.
 *
 * Het dashboard is een statische SPA, dus authenticatie loopt via een
 * Sanctum-bearertoken in localStorage in plaats van via cookies.
 */
const TOKEN_KEY = 'klimaatx.dashboard.token'

export function readToken(): string | null {
  if (import.meta.server) return null
  try {
    return window.localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function writeToken(token: string | null) {
  if (import.meta.server) return
  try {
    if (token === null) window.localStorage.removeItem(TOKEN_KEY)
    else window.localStorage.setItem(TOKEN_KEY, token)
  } catch {
    /* privémodus zonder opslag: de sessie duurt dan tot een refresh */
  }
}

export type ApiError = { status: number; message: string; errors?: Record<string, string[]> }

export function useApi() {
  const base = useRuntimeConfig().public.apiBase as string

  async function request<T>(path: string, options: { method?: string; body?: unknown; auth?: boolean } = {}): Promise<T> {
    const { method = 'GET', body, auth = true } = options
    const headers: Record<string, string> = { Accept: 'application/json' }

    if (body !== undefined) headers['Content-Type'] = 'application/json'

    if (auth) {
      const token = readToken()
      if (token) headers.Authorization = `Bearer ${token}`
    }

    const response = await fetch(`${base}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })

    const text = await response.text()
    const payload = text ? JSON.parse(text) : {}

    if (!response.ok) {
      if (response.status === 401 && auth) {
        writeToken(null)
        if (!useRoute().path.endsWith('/login')) await navigateTo('/dashboard/login')
      }

      throw {
        status: response.status,
        message: payload.message ?? 'Er ging iets mis bij het ophalen van gegevens.',
        errors: payload.errors,
      } satisfies ApiError
    }

    return payload as T
  }

  return {
    get: <T>(path: string) => request<T>(path),
    post: <T>(path: string, body?: unknown, auth = true) => request<T>(path, { method: 'POST', body, auth }),
    patch: <T>(path: string, body: unknown) => request<T>(path, { method: 'PATCH', body }),
  }
}
