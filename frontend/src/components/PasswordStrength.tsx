import { useEffect, useMemo, useState } from 'react'

/** Indicateur de force du mot de passe en direct (ROADMAP #20).
 * Règles :
 *  - 8 caractères min
 *  - 12+ caractères (bonus)
 *  - minuscule + MAJUSCULE
 *  - 1 chiffre
 *  - 1 caractère spécial
 * Score 0-5 → barre colorée. */
export default function PasswordStrength({ password }: { password: string }) {
  const [score, checks] = useMemo(() => {
    const p = password || ''
    const c = {
      length8: p.length >= 8,
      length12: p.length >= 12,
      upper: /[A-Z]/.test(p),
      lower: /[a-z]/.test(p),
      digit: /\d/.test(p),
      symbol: /[^A-Za-z0-9]/.test(p),
    }
    let s = 0
    if (c.length8) s++
    if (c.length12) s++
    if (c.lower && c.upper) s++
    if (c.digit) s++
    if (c.symbol) s++
    return [s, c] as const
  }, [password])

  const labels = ['Très faible', 'Faible', 'Moyen', 'Bon', 'Fort', 'Excellent']
  const colors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success', 'bg-success']
  const width = password ? `${(score / 5) * 100}%` : '0%'
  const color = colors[score]

  return (
    <div className="mt-2" style={{ fontSize: '0.8rem' }}>
      <div className="progress" style={{ height: '6px' }}>
        <div className={`progress-bar ${color}`} role="progressbar" style={{ width }} />
      </div>
      <div className="d-flex justify-content-between mt-1">
        <span className="text-muted">Force : <strong>{password ? labels[score] : '—'}</strong></span>
        {password && score < 3 && (
          <small className="text-muted">
            {!checks.length8 && '8+ car. '}
            {checks.length8 && !checks.length12 && '12+ car. '}
            {!(checks.lower && checks.upper) && 'Maj+min '}
            {!checks.digit && 'chiffre '}
            {!checks.symbol && 'symbole'}
          </small>
        )}
      </div>
    </div>
  )
}
