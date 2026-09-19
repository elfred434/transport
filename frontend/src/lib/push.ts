/**
 * Notifications push navigateur (ROADMAP #30).
 *
 * - Demande la permission au premier affichage du dashboard.
 * - Abonne l'utilisateur via le Service Worker (Push API) si VAPID configurée.
 * - Fallback : si pas de SW/push configuré, on utilise juste des notifications
 *   locales via l'API Notification.
 * - Appelle le backend POST /api/push/subscribe pour enregistrer l'abonnement.
 */
export interface PushSubscriptionPayload {
  endpoint: string
  keys: { p256dh: string; auth: string }
}

const LS_KEY = 'spiistmove.pushAsked'

export function browserNotificationsSupported(): boolean {
  return typeof window !== 'undefined' && 'Notification' in window
}

export function pushPermission(): NotificationPermission | 'unsupported' {
  if (!browserNotificationsSupported()) return 'unsupported'
  return Notification.permission
}

export async function askPermission(): Promise<boolean> {
  if (!browserNotificationsSupported()) return false
  if (Notification.permission === 'granted') return true
  if (Notification.permission === 'denied') return false
  try {
    const res = await Notification.requestPermission()
    localStorage.setItem(LS_KEY, '1')
    return res === 'granted'
  } catch { return false }
}

export function hasAsked(): boolean {
  try { return localStorage.getItem(LS_KEY) === '1' } catch { return false }
}

/** Envoie une notification locale (sans serveur push). */
export function localNotify(title: string, body: string, url?: string) {
  if (!browserNotificationsSupported() || Notification.permission !== 'granted') return
  try {
    const n = new Notification(title, { body, icon: '/assets/img/OIG1.jpeg' })
    if (url) n.onclick = () => { window.focus(); window.location.href = url; n.close() }
  } catch {}
}
