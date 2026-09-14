/**
 * Notation par étoiles (ROADMAP #33).
 * - N'utilise pas d'emojis : étoiles FontAwesome.
 * - Mode lecture (readOnly) ou interactif.
 */
interface StarRatingProps {
  value?: number
  /** Nombre d'étoiles affichées (défaut 5). */
  max?: number
  /** Afficher la valeur numérique à côté. */
  showValue?: boolean
  /** Nombre de votes (à afficher à côté). */
  count?: number | null
  /** Taille CSS. */
  size?: 'sm' | 'md' | 'lg'
  /** Appelé au clic en mode interactif. */
  onChange?: (v: number) => void
  /** Lecture seule par défaut. */
  readOnly?: boolean
}

function Star({ filled, half, size }: { filled: boolean; half?: boolean; size: string }) {
  const px = size === 'sm' ? '14px' : size === 'lg' ? '24px' : '18px'
  return (
    <span
      className="position-relative d-inline-block"
      style={{ width: px, height: px, lineHeight: px }}
      aria-hidden="true"
    >
      <i className="fa-regular fa-star" style={{ color: '#adb5bd', fontSize: px }} />
      {filled && !half && (
        <i className="fa-solid fa-star position-absolute top-0 start-0" style={{ color: '#f59e0b', fontSize: px }} />
      )}
      {half && (
        <i className="fa-solid fa-star-half-stroke position-absolute top-0 start-0" style={{ color: '#f59e0b', fontSize: px }} />
      )}
    </span>
  )
}

export default function StarRating({
  value = 0,
  max = 5,
  showValue = false,
  count,
  size = 'md',
  onChange,
  readOnly = true,
}: StarRatingProps) {
  const v = Math.max(0, Math.min(max, value))
  const interactive = !readOnly && !!onChange

  const click = (i: number) => {
    if (!interactive) return
    onChange?.(i + 1)
  }

  return (
    <span className="d-inline-flex align-items-center gap-1" role={interactive ? 'radiogroup' : undefined} aria-label={`Note ${v.toFixed(1)} sur ${max}`}>
      {Array.from({ length: max }).map((_, i) => {
        const filled = v >= i + 1
        const half = !filled && v > i && v < i + 1
        return (
          <button
            type="button"
            key={i}
            onClick={() => click(i)}
            disabled={!interactive}
            style={{
              border: 'none',
              background: 'transparent',
              padding: 0,
              cursor: interactive ? 'pointer' : 'default',
            }}
            aria-label={`Donner ${i + 1} étoile${i + 1 > 1 ? 's' : ''}`}
            aria-checked={filled}
            role={interactive ? 'radio' : undefined}
          >
            <Star filled={filled} half={half} size={size} />
          </button>
        )
      })}
      {showValue && (
        <span className="ms-2 small text-muted">
          <strong className="text-dark">{v.toFixed(1)}</strong>/{max}
          {typeof count === 'number' && <> · {count} avis</>}
        </span>
      )}
    </span>
  )
}
