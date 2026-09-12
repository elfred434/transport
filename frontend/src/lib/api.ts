/**
 * Client API — SpiistMove.
 *
 * Mode d'authentification (sécurisé XSS) :
 *  - Le backend pose les tokens `access_token` et `refresh_token` dans des
 *    cookies **HttpOnly** + Secure + SameSite=Lax (ils ne sont PAS lisibles
 *    en JS et ne peuvent pas être volés par une XSS).
 *  - Toutes les requêtes sont envoyées avec `credentials: 'include'` pour que
 *    les cookies soient transmis.
 *  - L'en-tête `Authorization: Bearer …` n'est plus nécessaire. On garde un
 *    fallback qui lit `localStorage['transport_token']` pour la
 *    rétrocompatibilité avec d'anciennes sessions ouvertes.
 *  - Sur 401, on tente `/api/auth/refresh` (avec cookies). Si ça marche, on
 *    rejoue la requête initiale ; sinon on nettoie et on redirige vers login.
 *  - Enveloppe {success, data|error} : on retourne directement `data`.
 *  - FormData supporté pour les uploads.
 */

export const TOKEN_KEY = 'transport_token'
export const REFRESH_KEY = 'transport_refresh'

export class ApiError extends Error {
  status: number
  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

export const Auth = {
  /** Déprécié : les tokens sont dans des cookies HttpOnly. Ces méthodes
   *  nettoient l'ancien localStorage (rétrocompatibilité) au chargement. */
  token(): string | null {
    const v = localStorage.getItem(TOKEN_KEY)
    if (v) localStorage.removeItem(TOKEN_KEY)
    return null
  },
  refresh(): string | null {
    const v = localStorage.getItem(REFRESH_KEY)
    if (v) localStorage.removeItem(REFRESH_KEY)
    return null
  },
  saveAccess(_token: string) { /* no-op: tokens now in HttpOnly cookies */ },
  saveRefresh(_token: string) { /* no-op */ },
  saveTokens(_access: string, _refresh?: string) { /* no-op */ },
  clear() {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(REFRESH_KEY)
    try {
      // Supprimer le cookie booléen spiistmove_logged_in
      document.cookie = 'spiistmove_logged_in=; Path=/; Max-Age=0; SameSite=Lax'
    } catch { /* noop */ }
  },
  /** Détecte la session via le cookie booléen spiistmove_logged_in posé par le backend. */
  isLoggedIn(): boolean {
    // Nettoyer les anciennes clés localStorage au premier appel (transition).
    try {
      if (localStorage.getItem(TOKEN_KEY)) localStorage.removeItem(TOKEN_KEY)
      if (localStorage.getItem(REFRESH_KEY)) localStorage.removeItem(REFRESH_KEY)
    } catch { /* noop */ }
    try {
      return document.cookie.split(';').some((c) => c.trim().startsWith('spiistmove_logged_in='))
    } catch { return false }
  },
}

// Empêche deux refresh parallèles
let _refreshPromise: Promise<boolean> | null = null

async function doRefresh(): Promise<boolean> {
  const rt = Auth.refresh()  // fallback localStorage
  try {
    const res = await fetch(absUrl('/api/auth/refresh'), {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': 'true' },
      // Si le refresh est en cookie, pas besoin de l'envoyer dans le body ;
      // sinon on l'envoie pour la rétrocompatibilité.
      body: rt ? JSON.stringify({ refresh: rt }) : '{}',
    })
    if (res.ok) {
      // Les nouveaux cookies sont automatiquement posés par le navigateur via Set-Cookie.
      // Si la réponse contient aussi un token JSON (rétrocompatibilité), on le sauvegarde.
      try {
        const data = await res.json()
        const newAccess = data?.data?.access || data?.access
        const newRefresh = data?.data?.refresh || data?.refresh
        if (newAccess) Auth.saveAccess(newAccess)
        if (newRefresh) Auth.saveRefresh(newRefresh)
      } catch { /* ignore */ }
      return true
    }
    return false
  } catch {
    return false
  }
}

const API_BASE = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/$/, '') ?? ''

function absUrl(path: string): string {
  if (/^https?:\/\//i.test(path)) return path
  return API_BASE + path
}

async function rawFetch<T>(method: string, path: string, body: BodyInit | null = null, headers: Record<string, string> = {}, isRetry = false): Promise<T> {
  // Token Bearer depuis localStorage seulement en fallback (anciennes sessions).
  const token = Auth.token()
  if (token && !headers['Authorization']) headers['Authorization'] = 'Bearer ' + token
  headers['ngrok-skip-browser-warning'] = 'true'

  let res: Response
  try {
    res = await fetch(absUrl(path), {
      method,
      headers,
      body,
      credentials: 'include', // envoie/reçoit les cookies HttpOnly
    })
  } catch {
    throw new ApiError("Serveur injoignable. Vérifiez votre connexion ou l'adresse du serveur.", 0)
  }

  // 401 → tenter refresh silencieux (1 seule fois)
  if (res.status === 401 && !isRetry) {
    if (!_refreshPromise) _refreshPromise = doRefresh()
    const ok = await _refreshPromise
    _refreshPromise = null
    if (ok) return rawFetch<T>(method, path, body, headers, true)
    Auth.clear()
    if (window.location.pathname !== '/login') {
      window.location.href = '/login?expired=1'
    }
    throw new ApiError('Session expirée', 401)
  }

  const text = await res.text()
  let data: any = null
  try { data = text ? JSON.parse(text) : null } catch { /* non-JSON */ }

  if (!res.ok) {
    if (res.status === 401 && isRetry) {
      Auth.clear()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login?expired=1'
      }
    }
    // 403 EMAIL_NOT_VERIFIED → redirection
    if (res.status === 403) {
      const code = data?.error?.code || (typeof data?.error === 'object' ? null : null)
      if (code === 'EMAIL_NOT_VERIFIED') {
        const email = data?.error?.email || ''
        if (email) sessionStorage.setItem('verify_email', email)
        if (window.location.pathname !== '/verify-email') {
          window.location.href = `/verify-email${email ? '?email=' + encodeURIComponent(email) : ''}`
        }
      }
    }
    // 429 Too Many Requests
    if (res.status === 429) {
      const retry = res.headers.get('Retry-After')
      const suffix = retry ? ` Réessaie dans ${retry}s.` : ''
      throw new ApiError((data?.error || 'Trop de requêtes.') + suffix, 429)
    }
    const msg =
      (data?.error?.message) ||
      (data?.error && typeof data.error === 'string' ? data.error : null) ||
      (data?.message) ||
      'Erreur HTTP ' + res.status
    throw new ApiError(msg, res.status)
  }

  return (data && data.data !== undefined ? data.data : data) as T
}

type Body = Record<string, unknown> | null | undefined

async function request<T>(method: string, path: string, body?: Body | FormData): Promise<T> {
  let payload: BodyInit | null = null
  const headers: Record<string, string> = {}
  if (body instanceof FormData) {
    payload = body
  } else if (body != null) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  return rawFetch<T>(method, path, payload, headers, false)
}

export async function authResponse(res: any) {
  /* Sauvegarde access+refresh en fallback localStorage. */
  const d = res?.data ?? res
  if (d?.token) Auth.saveAccess(d.token)
  if (d?.refresh) Auth.saveRefresh(d.refresh)
}

export const api = {
  get: <T = unknown>(path: string) => request<T>('GET', path),
  post: <T = unknown>(path: string, body?: Body | FormData) => request<T>('POST', path, body ?? null),
  put: <T = unknown>(path: string, body?: Body | FormData) => request<T>('PUT', path, body ?? null),
  del: <T = unknown>(path: string) => request<T>('DELETE', path),
  upload: <T = unknown>(
    path: string,
    fields: Record<string, string | number | null | undefined | boolean>,
    files: Record<string, File | null | undefined> = {},
  ) => {
    const fd = new FormData()
    Object.entries(fields || {}).forEach(([k, v]) => {
      if (v !== undefined && v !== null) fd.append(k, String(v))
    })
    Object.entries(files).forEach(([k, f]) => { if (f) fd.append(k, f) })
    return request<T>('POST', path, fd)
  },
}
