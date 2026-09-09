/**
 * Helpers de formatage — port de UI.money/date/datetime/badge/img.
 *
 * React échappe le texte par défaut : `UI.esc` n'a plus d'équivalent nécessaire
 * (et n'en aura jamais besoin ici), les valeurs sont rendues via JSX.
 */
import { useState } from 'react'

export function money(v: unknown): string {
  const n = Number(v || 0)
  return (
    n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' F CFA'
  )
}

export function date(v: unknown): string {
  if (!v) return '—'
  const d = new Date(String(v).replace(' ', 'T'))
  if (isNaN(d.getTime())) return String(v)
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function datetime(v: unknown): string {
  if (!v) return '—'
  const d = new Date(String(v).replace(' ', 'T'))
  if (isNaN(d.getTime())) return String(v)
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** Badge de statut — même table de correspondance que UI.badge(). */
const BADGES: Record<string, [string, string]> = {
  en_attente: ['En attente', 'bg-warning text-dark'],
  approuve: ['Approuvé', 'bg-success'],
  refuse: ['Refusé', 'bg-danger'],
  accepte: ['Accepté', 'bg-success'],
  annule: ['Annulé', 'bg-secondary'],
  termine: ['Terminé', 'bg-secondary'],
  paye: ['Payé', 'bg-success'],
  echec: ['Échec', 'bg-danger'],
  'En attente': ['En attente', 'bg-secondary'],
  'En cours': ['En cours', 'bg-info text-dark'],
  'Livré': ['Livré', 'bg-success'],
}

export function StatusBadge({ statut }: { statut?: string | null }) {
  const [label, cls] = BADGES[statut || ''] || [statut || '—', 'bg-light text-dark']
  return <span className={`badge ${cls}`}>{label}</span>
}

/** Image avec repli sur le placeholder (équivalent de UI.img + onerror). */
export function SmartImg({
  url,
  alt = '',
  className = 'rounded',
}: {
  url?: string | null
  alt?: string
  className?: string
}) {
  const placeholder = '/assets/img/placeholder.svg'
  const [broken, setBroken] = useState(false)
  const src = url && !broken ? url : placeholder
  return (
    <img
      src={src}
      alt={alt}
      className={className}
      onError={() => setBroken(true)}
    />
  )
}

/** Étoiles de note (arrondi à la demi-étoile la plus proche). */
export function Stars({ note }: { note: number | null }) {
  if (note === null || note === undefined) return <span className="text-muted small">Pas encore de note</span>
  const full = Math.round(Number(note))
  return (
    <span className="text-warning">
      {[1, 2, 3, 4, 5].map((i) => (
        <i key={i} className={i <= full ? 'fa-solid fa-star' : 'fa-regular fa-star'} />
      ))}
    </span>
  )
}
