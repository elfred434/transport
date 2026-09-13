import { useState, useCallback } from 'react'

/** Générateur de mot de passe fort aléatoire.
 *
 * Usage :
 *   <PasswordGenerator onSelect={(pwd) => setField('password', pwd)} />
 *
 * Génère par défaut un mot de passe de 16 caractères (majuscules, minuscules,
 * chiffres, symboles), avec bouton "copier", régénérer, et indicateur de force.
 */

const LOWER = 'abcdefghijklmnopqrstuvwxyz'
const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
const DIGITS = '0123456789'
const SYMBOLS = '!@#$%^&*()-_=+[]{};:,.?/'

function secureRandomInt(max: number): number {
  const arr = new Uint32Array(1)
  crypto.getRandomValues(arr)
  return arr[0] % max
}

function generatePassword(length: number, useUpper: boolean, useDigits: boolean, useSymbols: boolean): string {
  const pools: string[] = [LOWER]
  const required: string[] = []
  if (useUpper) { pools.push(UPPER); required.push(UPPER) }
  if (useDigits) { pools.push(DIGITS); required.push(DIGITS) }
  if (useSymbols) { pools.push(SYMBOLS); required.push(SYMBOLS) }
  const all = pools.join('')

  // S'assurer qu'au moins un caractère de chaque pool sélectionné est présent
  const chars: string[] = []
  for (const pool of required) chars.push(pool[secureRandomInt(pool.length)])
  while (chars.length < length) {
    chars.push(all[secureRandomInt(all.length)])
  }
  // Fisher-Yates
  for (let i = chars.length - 1; i > 0; i--) {
    const j = secureRandomInt(i + 1)
    ;[chars[i], chars[j]] = [chars[j], chars[i]]
  }
  return chars.join('')
}

function scorePassword(pwd: string): { score: number; label: string; color: string } {
  let score = 0
  if (pwd.length >= 8) score += 1
  if (pwd.length >= 12) score += 1
  if (pwd.length >= 16) score += 1
  if (/[A-Z]/.test(pwd)) score += 1
  if (/[0-9]/.test(pwd)) score += 1
  if (/[^A-Za-z0-9]/.test(pwd)) score += 1
  if (score <= 2) return { score, label: 'Faible', color: '#e74c3c' }
  if (score <= 4) return { score, label: 'Moyen', color: '#f39c12' }
  return { score, label: 'Fort', color: '#27ae60' }
}

interface PasswordGeneratorProps {
  onSelect: (password: string) => void
  /** Référence à l'input password pour pouvoir lui donner le focus après génération. */
  inputRef?: React.RefObject<HTMLInputElement | null>
}

export default function PasswordGenerator({ onSelect, inputRef }: PasswordGeneratorProps) {
  const [pwd, setPwd] = useState<string>('')
  const [length, setLength] = useState<number>(16)
  const [useUpper, setUseUpper] = useState(true)
  const [useDigits, setUseDigits] = useState(true)
  const [useSymbols, setUseSymbols] = useState(true)
  const [copied, setCopied] = useState(false)
  const [open, setOpen] = useState(false)

  const doGenerate = useCallback(() => {
    const p = generatePassword(length, useUpper, useDigits, useSymbols)
    setPwd(p)
    setCopied(false)
    onSelect(p)
    // trigger input event pour React (sinon React ne détecte pas le set .value)
    requestAnimationFrame(() => {
      const el = inputRef?.current
      if (el) {
        el.value = p
        el.dispatchEvent(new Event('input', { bubbles: true }))
        el.focus()
      }
    })
  }, [length, useUpper, useDigits, useSymbols, onSelect, inputRef])

  const copy = useCallback(async () => {
    if (!pwd) return
    try {
      await navigator.clipboard.writeText(pwd)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      // Fallback
      const ta = document.createElement('textarea')
      ta.value = pwd
      document.body.appendChild(ta)
      ta.select()
      try { document.execCommand('copy') } catch { /* noop */ }
      document.body.removeChild(ta)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }, [pwd])

  const score = pwd ? scorePassword(pwd) : null

  return (
    <div style={{ marginTop: 4 }}>
      <button type="button" className="btn btn-sm btn-outline-secondary"
        onClick={() => { setOpen(v => !v); if (!open) doGenerate() }}
        style={{ fontSize: 12, padding: '2px 10px' }}>
        <i className="fa-solid fa-wand-magic-sparkles me-1"></i>
        {open ? 'Masquer' : 'Générer un mot de passe fort'}
      </button>

      {open && (
        <div className="card card-body mt-2 p-3" style={{ background: '#f8f9fa', border: '1px solid #dee2e6', fontSize: 13 }}>
          <div className="d-flex align-items-center gap-2 mb-2">
            <input
              readOnly
              value={pwd || 'Cliquez sur « Régénérer »'}
              className="form-control text-center font-monospace"
              style={{
                fontSize: 16,
                letterSpacing: 2,
                flex: 1,
                background: '#fff',
                border: '1px solid #ced4da',
                padding: '8px 12px',
              }}
            />
            <button type="button" className="btn btn-primary btn-sm" onClick={doGenerate} title="Régénérer">
              <i className="fa-solid fa-rotate"></i>
            </button>
            <button type="button" className={`btn btn-sm ${copied ? 'btn-success' : 'btn-outline-secondary'}`}
              onClick={copy} title="Copier" disabled={!pwd}>
              <i className={`fa-solid ${copied ? 'fa-check' : 'fa-copy'}`}></i>
              {copied ? ' Copié !' : ''}
            </button>
          </div>

          {score && (
            <div className="mb-2">
              <div className="d-flex justify-content-between" style={{ fontSize: 11 }}>
                <span>Force : <strong style={{ color: score.color }}>{score.label}</strong></span>
                <span>{pwd.length} caractères</span>
              </div>
              <div style={{
                height: 5, background: '#e9ecef', borderRadius: 3, overflow: 'hidden', marginTop: 3
              }}>
                <div style={{
                  width: `${(score.score / 6) * 100}%`,
                  height: '100%',
                  background: score.color,
                  transition: 'width .2s',
                }} />
              </div>
            </div>
          )}

          <div className="d-flex align-items-center gap-3 flex-wrap" style={{ fontSize: 12 }}>
            <label className="d-flex align-items-center gap-1 mb-0">
              Longueur :
              <input type="range" min={8} max={32} value={length}
                onChange={(e) => setLength(Number(e.target.value))}
                style={{ width: 100 }} />
              <strong>{length}</strong>
            </label>
            <label className="d-flex align-items-center gap-1 mb-0">
              <input type="checkbox" checked={useUpper} onChange={(e) => setUseUpper(e.target.checked)} /> Majuscules
            </label>
            <label className="d-flex align-items-center gap-1 mb-0">
              <input type="checkbox" checked={useDigits} onChange={(e) => setUseDigits(e.target.checked)} /> Chiffres
            </label>
            <label className="d-flex align-items-center gap-1 mb-0">
              <input type="checkbox" checked={useSymbols} onChange={(e) => setUseSymbols(e.target.checked)} /> Symboles
            </label>
          </div>
          <div className="text-muted mt-1" style={{ fontSize: 11 }}>
            <i className="fa-solid fa-info-circle me-1"></i>
            Ce mot de passe est généré localement dans ton navigateur (aucune donnée envoyée).
            Pense à le sauvegarder dans ton gestionnaire de mots de passe.
          </div>
        </div>
      )}
    </div>
  )
}
