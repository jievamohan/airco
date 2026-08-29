/**
 * Dunne client voor de KlimaatX-API.
 *
 * Het dashboard is een statische SPA, dus authenticatie loopt via een
 * Sanctum-bearertoken in de browseropslag in plaats van via cookies.
 */
const TOKEN_KEY = 'klimaatx.dashboard.token'

/**
 * Waar het token blijft staan, bepaalt hoe lang je ingelogd blijft.
 *
 * Zonder "onthoud mij" gaat hij naar sessionStorage: die is leeg zodra het
 * tabblad of de browser dicht gaat, wat je wilt op een gedeelde computer. Met
 * "onthoud mij" naar localStorage, en dan bepaalt de vervaldatum van het token
 * op de server wanneer het afgelopen is.
 */
function stores(): Storage[] {
  return [window.sessionStorage, window.localStorage]
}

export function readToken(): string | null {
  if (import.meta.server) return null
  try {
    for (const store of stores()) {
      const token = store.getItem(TOKEN_KEY)
      if (token) return token
    }
  } catch {
    /* privémodus zonder opslag */
  }
  return null
}

export function writeToken(token: string | null, remember = false) {
  if (import.meta.server) return
  try {
    // Altijd eerst beide legen, anders blijft er een oud token achter in de
    // store die deze keer niet gebruikt wordt.
    for (const store of stores()) store.removeItem(TOKEN_KEY)

    if (token !== null) {
      const store = remember ? window.localStorage : window.sessionStorage
      store.setItem(TOKEN_KEY, token)
    }
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

    let response: Response

    try {
      response = await fetch(`${base}${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      })
    } catch {
      // fetch werpt alleen bij een netwerk- of CORS-fout; het kale
      // "Failed to fetch" van de browser zegt niemand iets.
      throw {
        status: 0,
        message:
          `De API op ${base} is niet bereikbaar. Draait de api-container, ` +
          `en staat ${window.location.origin} in DASHBOARD_ORIGINS?`,
      } satisfies ApiError
    }

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
