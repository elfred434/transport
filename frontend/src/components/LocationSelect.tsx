import { useEffect, useMemo, useRef, useState } from 'react'
import { PAYS, villesDuPays } from '../lib/pays'

/* ------------------------------------------------------------------ */
/*  CountrySelect : select de pays avec champ de recherche intégré    */
/*  (combo-box custom, léger, sans dépendance).                        */
/* ------------------------------------------------------------------ */

interface CountrySelectProps {
  value: string                    // nom du pays (ou code) actuellement choisi
  onChange: (nomPays: string) => void
  placeholder?: string
  required?: boolean
  id?: string
  className?: string
  label?: string
}

export function CountrySelect({ value, onChange, placeholder = 'Rechercher un pays…', required, id, className, label }: CountrySelectProps) {
  const [q, setQ] = useState(value)
  const [open, setOpen] = useState(false)
  const [hl, setHl] = useState(0)
  const wrapRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => { setQ(value) }, [value])

  // Fermeture au clic extérieur
  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase()
    if (!s) return PAYS
    return PAYS.filter(p =>
      p.nom.toLowerCase().includes(s) ||
      p.code.toLowerCase().includes(s),
    )
  }, [q])

  function pick(p: typeof PAYS[number]) {
    setQ(p.nom)
    onChange(p.nom)
    setOpen(false)
    inputRef.current?.blur()
  }

  function onKey(e: React.KeyboardEvent<HTMLInputElement>) {
    if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { setOpen(true); return }
    if (!open) return
    if (e.key === 'ArrowDown') { e.preventDefault(); setHl(i => Math.min(i + 1, filtered.length - 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setHl(i => Math.max(i - 1, 0)) }
    else if (e.key === 'Enter') {
      e.preventDefault()
      if (filtered[hl]) pick(filtered[hl])
    } else if (e.key === 'Escape') { setOpen(false) }
  }

  return (
    <div className={`position-relative ${className || ''}`} ref={wrapRef}>
      {label && <label className="form-label fw-bold">{label}</label>}
      <input
        ref={inputRef}
        id={id}
        type="text"
        className="form-control form-control-lg"
        placeholder={placeholder}
        value={q}
        required={required}
        autoComplete="off"
        onFocus={() => { setOpen(true); setHl(0) }}
        onChange={(e) => {
          setQ(e.target.value)
          setOpen(true)
          setHl(0)
          onChange(e.target.value)  // valeur libre autorisée pendant la saisie
        }}
        onKeyDown={onKey}
      />
      <i className="fa-solid fa-chevron-down position-absolute" style={{ right: 14, top: label ? 42 : 14, fontSize: 12, color: '#888', pointerEvents: 'none' }} />
      {open && filtered.length > 0 && (
        <ul className="list-group position-absolute w-100 shadow-sm" style={{ zIndex: 50, maxHeight: 260, overflowY: 'auto' }}>
          {filtered.map((p, i) => (
            <button
              type="button"
              key={p.code}
              className={`list-group-item list-group-item-action d-flex align-items-center border-0 py-2 px-3 ${i === hl ? 'active' : ''}`}
              onMouseDown={(e) => { e.preventDefault(); pick(p) }}
              onMouseEnter={() => setHl(i)}
              style={{ cursor: 'pointer' }}
            >
              <span style={{ fontSize: 20, marginRight: 10 }}>{p.drapeau}</span>
              <span>{p.nom}</span>
              <small className="text-muted ms-auto">{p.code}</small>
            </button>
          ))}
        </ul>
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  CitySelect : select de ville qui se charge à partir du pays       */
/*  choisi. Permet aussi la saisie libre si la ville n'est pas listée. */
/* ------------------------------------------------------------------ */

interface CitySelectProps {
  pays: string                      // pays sélectionné (pour filtrer la liste)
  value: string
  onChange: (ville: string) => void
  placeholder?: string
  required?: boolean
  id?: string
  className?: string
  label?: string
}

export function CitySelect({ pays, value, onChange, placeholder = 'Choisir une ville…', required, id, className, label }: CitySelectProps) {
  const [q, setQ] = useState(value)
  const [open, setOpen] = useState(false)
  const [hl, setHl] = useState(0)
  const wrapRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => { setQ(value) }, [value])

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  const villes = useMemo(() => villesDuPays(pays), [pays])

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase()
    if (!s) return villes
    return villes.filter(v => v.toLowerCase().includes(s))
  }, [villes, q])

  function pick(v: string) {
    setQ(v)
    onChange(v)
    setOpen(false)
    inputRef.current?.blur()
  }

  function onKey(e: React.KeyboardEvent<HTMLInputElement>) {
    if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { setOpen(true); return }
    if (!open) return
    if (e.key === 'ArrowDown') { e.preventDefault(); setHl(i => Math.min(i + 1, filtered.length - 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setHl(i => Math.max(i - 1, 0)) }
    else if (e.key === 'Enter') {
      e.preventDefault()
      if (filtered[hl]) pick(filtered[hl])
    } else if (e.key === 'Escape') { setOpen(false) }
  }

  const disabled = !pays

  return (
    <div className={`position-relative ${className || ''}`} ref={wrapRef}>
      {label && <label className="form-label fw-bold">{label}</label>}
      <input
        ref={inputRef}
        id={id}
        type="text"
        className="form-control form-control-lg"
        placeholder={disabled ? 'Choisissez d\'abord un pays' : placeholder}
        value={q}
        required={required}
        disabled={disabled}
        autoComplete="off"
        onFocus={() => { if (!disabled) { setOpen(true); setHl(0) } }}
        onChange={(e) => {
          setQ(e.target.value)
          setOpen(true); setHl(0)
          onChange(e.target.value)
        }}
        onKeyDown={onKey}
      />
      <i className="fa-solid fa-city position-absolute" style={{ right: 14, top: label ? 42 : 14, fontSize: 12, color: '#888', pointerEvents: 'none' }} />
      {open && !disabled && filtered.length > 0 && (
        <ul className="list-group position-absolute w-100 shadow-sm" style={{ zIndex: 50, maxHeight: 220, overflowY: 'auto' }}>
          {filtered.map((v, i) => (
            <button
              type="button"
              key={v}
              className={`list-group-item list-group-item-action py-2 px-3 border-0 ${i === hl ? 'active' : ''}`}
              onMouseDown={(e) => { e.preventDefault(); pick(v) }}
              onMouseEnter={() => setHl(i)}
              style={{ cursor: 'pointer' }}
            >
              <i className="fa-solid fa-location-dot text-muted me-2" />{v}
            </button>
          ))}
        </ul>
      )}
      {open && !disabled && filtered.length === 0 && q.trim() && (
        <div className="list-group position-absolute w-100 shadow-sm small text-muted p-2" style={{ zIndex: 50 }}>
          Aucune ville correspondante — saisie libre acceptée.
        </div>
      )}
    </div>
  )
}
