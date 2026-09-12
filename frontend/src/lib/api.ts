/**
 * Client API — port TypeScript de js/api.js (frontend vanilla).
 *
 * Contrat :
 *  - access token Bearer dans localStorage ('transport_token'), refresh dans
 *    localStorage ('transport_refresh') ; l'access dure 2h, le refresh 7j.
 *  - Enveloppe {success, data|error} : on retourne directement `data`.
 *  - Sur 401, on tente un /api/auth/refresh avec le refresh token ; si ça
 *    marche on rejoue la requête initiale, sinon on déconnecte.
 *  - FormData supporté pour les uploads.
 *  - ngrok-skip-browser-warning pour éviter la page d'avertissement ngrok.
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
  token(): string | null { return localStorage.getItem(TOKEN_KEY) },
  refresh(): string | null { return localStorage.getItem(REFRESH_KEY) },
  saveAccess(token: string) { localStorage.setItem(TOKEN_KEY, token) },
  saveRefresh(token: string) { localStorage.setItem(REFRESH_KEY, token) },
  saveTokens(access: string, refresh?: string) {
    this.saveAccess(access)
    if (refresh) this.saveRefresh(refresh)
  },
  clear() {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(REFRESH_KEY)
  },
  isLoggedIn(): boolean { return !!this.token() },
}

// Empêche deux refresh parallèles
let _refreshPromise: Promise<string | null> | null = null

async function doRefresh(): Promise<string | null> {
  const rt = Auth.refresh()
  if (!rt) return null
  try {
    const res = await fetch('/api/auth/refresh', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh: rt }),
    })
    if (!res.ok) return null
    const data = await res.json()
    const newAccess = data?.data?.access || data?.access
    const newRefresh = data?.data?.refresh || data?.refresh
    if (newAccess) {
      Auth.saveAccess(newAccess)
      if (newRefresh) Auth.saveRefresh(newRefresh)
      return newAccess
    }
    return null
  } catch {
    return null
  }
}

export async function authResponse(res: any) {
  /* Sauvegarde access+refresh depuis une réponse login/register */
  const d = res?.data ?? res
  if (d?.token) Auth.saveAccess(d.token)
  if (d?.refresh) Auth.saveRefresh(d.refresh)
}

type Body = Record<string, unknown> | null | undefined

// Base URL de l'API en production.
// En dev, Vite proxy les chemins relatifs /api → http://127.0.0.1:8000.
// En production (Vercel/Render), VITE_API_BASE_URL doit pointer vers
// l'URL HTTPS du backend Render (ex: https://spiistmove-api.onrender.com).
// S'il n'est pas défini, on utilise des chemins relatifs (utile si Nginx
// sert frontend + backend sur le même domaine).
const API_BASE = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/$/, '') ?? ''

function absUrl(path: string): string {
  if (/^https?:\/\//i.test(path)) return path
  return API_BASE + path
}

async function rawFetch<T>(method: string, path: string, body: Body | FormData = null, isRetry = false): Promise<T> {
  const headers: Record<string, string> = {}
  const token = Auth.token()
  if (token) headers['Authorization'] = 'Bearer ' + token
  headers['ngrok-skip-browser-warning'] = 'true'

  let payload: BodyInit | null = null
  if (body instanceof FormData) {
    payload = body
  } else if (body != null) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  let res: Response
  try {
    res = await fetch(absUrl(path), { method, headers, body: payload })
  } catch {
    throw new ApiError("Serveur injoignable. Vérifiez votre connexion ou l'adresse du serveur.", 0)
  }

  // 401 + token présent → tenter refresh silencieux (1 seule fois)
  if (res.status === 401 && Auth.isLoggedIn() && !isRetry) {
    if (!_refreshPromise) _refreshPromise = doRefresh()
    const newAccess = await _refreshPromise
    _refreshPromise = null
    if (newAccess) return rawFetch<T>(method, path, body, true)
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
    // 401 : refresh possible déjà géré ci-dessus → logout
    if (res.status === 401 && Auth.isLoggedIn() && isRetry) {
      Auth.clear()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login?expired=1'
      }
    }
    // 403 EMAIL_NOT_VERIFIED : rediriger vers la page de vérification
    if (res.status === 403 && data?.error?.code === 'EMAIL_NOT_VERIFIED') {
      const email = data.error.email || ''
      if (email) sessionStorage.setItem('verify_email', email)
      if (window.location.pathname !== '/verify-email') {
        window.location.href = `/verify-email${email ? '?email=' + encodeURIComponent(email) : ''}`
      }
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

async function request<T>(method: string, path: string, body: Body | FormData = null): Promise<T> {
  return rawFetch<T>(method, path, body, false)
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
