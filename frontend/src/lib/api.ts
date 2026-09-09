/**
 * Client API — port TypeScript de js/api.js (frontend vanilla).
 *
 * Contrat identique :
 *  - jeton Bearer stocké dans localStorage sous la clé 'transport_token' ;
 *  - enveloppe {success, data|error} : `data` est désenveloppé et retourné ;
 *  - 401 avec jeton présent → effacement du jeton + redirection /login ;
 *  - requêtes en URLs relatives : '/api/...' passe par le proxy Vite (dev)
 *    ou par le domaine unique (prod).
 */

export const TOKEN_KEY = 'transport_token'

export class ApiError extends Error {
  status: number
  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

export const Auth = {
  token(): string | null {
    return localStorage.getItem(TOKEN_KEY)
  },
  save(token: string) {
    localStorage.setItem(TOKEN_KEY, token)
  },
  clear() {
    localStorage.removeItem(TOKEN_KEY)
  },
  isLoggedIn(): boolean {
    return !!Auth.token()
  },
}

type Body = Record<string, unknown> | null

async function request<T>(method: string, path: string, body: Body | FormData = null): Promise<T> {
  const headers: Record<string, string> = {}
  const token = Auth.token()
  if (token) headers['Authorization'] = 'Bearer ' + token

  let payload: BodyInit | null = null
  if (body instanceof FormData) {
    payload = body // Content-Type défini automatiquement par le navigateur
  } else if (body !== null) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  let res: Response
  try {
    res = await fetch(path, { method, headers, body: payload })
  } catch {
    throw new ApiError("Serveur injoignable. Vérifiez que l'API est démarrée.", 0)
  }

  const text = await res.text()
  let data: { data?: T; error?: string } | null = null
  try {
    data = text ? JSON.parse(text) : null
  } catch {
    /* réponse non-JSON */
  }

  if (!res.ok) {
    // Jeton présent mais rejeté (401) : session expirée/révoquée.
    if (res.status === 401 && Auth.isLoggedIn()) {
      Auth.clear()
      window.location.href = '/login'
      throw new ApiError('Session expirée', 401)
    }
    throw new ApiError((data && data.error) || 'Erreur HTTP ' + res.status, res.status)
  }

  return (data && data.data !== undefined ? data.data : data) as T
}

export const api = {
  get: <T = unknown>(path: string) => request<T>('GET', path),
  post: <T = unknown>(path: string, body?: Body) => request<T>('POST', path, body ?? null),
  del: <T = unknown>(path: string) => request<T>('DELETE', path),
  upload: <T = unknown>(
    path: string,
    fields: Record<string, string | number | null | undefined>,
    files: Record<string, File | null | undefined> = {},
  ) => {
    const fd = new FormData()
    Object.entries(fields || {}).forEach(([k, v]) => {
      if (v !== undefined && v !== null) fd.append(k, String(v))
    })
    Object.entries(files).forEach(([k, f]) => {
      if (f) fd.append(k, f)
    })
    return request<T>('POST', path, fd)
  },
}
