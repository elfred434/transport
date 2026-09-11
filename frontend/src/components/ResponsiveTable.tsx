import { useState, type ReactNode } from 'react'

/* ------------------------------------------------------------------ */
/*  ResponsiveTable : tableau Bootstrap sur desktop, cartes + modale  */
/*  de détails sur mobile (< 768px).                                   */
/* ------------------------------------------------------------------ */

export interface Column<T> {
  /** Clé unique (pour le key React). */
  key: string
  /** Libellé d'en-tête (desktop) et de ligne dans la modale. */
  label: string
  /** Rendu custom de la cellule. Si absent, on utilise row[key]. */
  render?: (row: T) => ReactNode
  /** Si true, cette colonne s'affiche EN PLUS sur la carte mobile
   *  (par défaut, seule la première colonne est le titre, les autres sont
   *  dans la modale). Utilisez-le pour les infos les + importantes
   *  (statut, prix, destination…). */
  primaryOnMobile?: boolean
  /** Si true, la colonne est complètement masquée sur la carte mobile
   *  (actions, images…). Reste visible dans la modale. */
  hideOnCard?: boolean
  /** Classe(s) de cellule (optionnel). */
  className?: string
  /** Classe(s) d'en-tête (optionnel). */
  headerClassName?: string
}

interface Props<T> {
  columns: Column<T>[]
  data: T[] | null | undefined
  /** Colonne servant de titre principal sur la carte mobile (défaut : 1re colonne). */
  titleKey?: string
  /** Colonne affichée en sous-titre sur la carte mobile (optionnel). */
  subtitleKey?: string
  /** Clé unique pour chaque ligne. */
  rowKey: (row: T, idx: number) => string | number
  /** État de chargement. */
  loading?: boolean
  /** Message quand la liste est vide (texte ou JSX). */
  emptyText?: ReactNode
  /** Colonne d'actions (boutons), rendue à la fois dans le tableau et
   *  directement sur la carte mobile (pas dans la modale). */
  actions?: (row: T) => ReactNode
  /** Classes additionnelles sur le tableau. */
  className?: string
  /** Classes sur le conteneur desktop (.table-responsive). */
  wrapperClassName?: string
}

export default function ResponsiveTable<T>({
  columns,
  data,
  titleKey,
  subtitleKey,
  rowKey,
  loading = false,
  emptyText = 'Aucun résultat.',
  actions,
  className = '',
  wrapperClassName = '',
}: Props<T>) {
  const [details, setDetails] = useState<T | null>(null)

  const titleCol = columns.find(c => c.key === titleKey) || columns[0]
  const subtitleCol = columns.find(c => c.key === subtitleKey)
  const cellValue = (col: Column<T>, row: T): ReactNode => {
    if (col.render) return col.render(row)
    const v = (row as Record<string, unknown>)[col.key]
    return v === null || v === undefined || v === '' ? '—' : String(v)
  }
  // Normalise data : peut être un tableau direct, ou une réponse paginée Laravel/DRF
  // {data: [...], pagination/meta: {...}}. On extrait toujours un tableau.
  const rows: T[] = (() => {
    if (Array.isArray(data)) return data
    if (data && typeof data === 'object' && 'data' in data) {
      const inner = (data as { data?: unknown }).data
      if (Array.isArray(inner)) return inner as T[]
    }
    // null / undefined / objet non paginé sans data=array → tableau vide
    return []
  })()
  // Colonnes à afficher en plus du titre sur la carte mobile
  const cardPrimaries = columns.filter(c => c.primaryOnMobile && !c.hideOnCard && c.key !== titleCol?.key)

  return (
    <>
      {/* ======================== DESKTOP : tableau classique ======================== */}
      <div className={`table-responsive rtable-desktop ${wrapperClassName}`}>
        <table className={`table align-middle ${className}`}>
          <thead className="table-light">
            <tr>
              {columns.map(c => (
                <th key={c.key} className={c.headerClassName || ''}>{c.label}</th>
              ))}
              {actions && <th className="text-end">Actions</th>}
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={columns.length + (actions ? 1 : 0)} className="text-center text-muted">
                  Chargement…
                </td>
              </tr>
            )}
            {!loading && rows.length === 0 && (
              <tr>
                <td colSpan={columns.length + (actions ? 1 : 0)} className="text-center text-muted">
                  {emptyText}
                </td>
              </tr>
            )}
            {!loading && rows.map((row, idx) => (
              <tr key={rowKey(row, idx)}>
                {columns.map(c => (
                  <td key={c.key} className={c.className || ''}>{cellValue(c, row)}</td>
                ))}
                {actions && (
                  <td className="text-end text-nowrap">
                    <div className="d-flex flex-wrap gap-1 justify-content-end">{actions(row)}</div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ======================== MOBILE : cartes ======================== */}
      <div className="rtable-mobile">
        {loading && <p className="text-muted small p-2">Chargement…</p>}
        {!loading && rows.length === 0 && (
          <p className="text-muted text-center p-3">{emptyText}</p>
        )}
        {rows.map((row, idx) => (
          <div key={rowKey(row, idx)} className="rtable-card card shadow-sm mb-2">
            <div className="card-body p-3">
              <div className="d-flex justify-content-between align-items-start gap-2 mb-1">
                <h6 className="mb-0 fw-bold flex-grow-1">{cellValue(titleCol, row)}</h6>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-primary rtable-more"
                  onClick={() => setDetails(row)}
                  aria-label="Voir les détails"
                >
                  <i className="fa-solid fa-ellipsis-vertical" />
                </button>
              </div>
              {subtitleCol && (
                <div className="small text-muted mb-1">{cellValue(subtitleCol, row)}</div>
              )}
              {cardPrimaries.length > 0 && (
                <div className="d-flex flex-wrap gap-2 small mt-2 rtable-tags">
                  {cardPrimaries.map(c => (
                    <span key={c.key} className="rtable-tag">
                      <span className="text-muted me-1">{c.label}:</span>
                      <span className="fw-semibold">{cellValue(c, row)}</span>
                    </span>
                  ))}
                </div>
              )}
              {actions && (
                <div className="d-flex flex-wrap gap-1 mt-2">{actions(row)}</div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* ======================== MODALE DÉTAILS (mobile) ======================== */}
      {details && (
        <div
          className="modal fade show d-block rtable-modal"
          tabIndex={-1}
          style={{ backgroundColor: 'rgba(0,0,0,.5)', zIndex: 300 }}
          onClick={() => setDetails(null)}
        >
          <div className="modal-dialog modal-dialog-centered" onClick={(e) => e.stopPropagation()}>
            <div className="modal-content">
              <div className="modal-header bg-light">
                <h5 className="modal-title d-flex align-items-center gap-2">
                  <i className="fa-solid fa-circle-info text-primary" />
                  {cellValue(titleCol, details)}
                </h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setDetails(null)}
                  aria-label="Fermer"
                />
              </div>
              <div className="modal-body p-0">
                <ul className="list-group list-group-flush">
                  {columns.map(c => {
                    if (c.hideOnCard && c.key === 'actions') return null
                    return (
                      <li key={c.key} className="list-group-item d-flex justify-content-between align-items-start gap-3 py-2 px-3">
                        <span className="text-muted small" style={{ flex: '0 0 40%' }}>{c.label}</span>
                        <span className="text-end fw-semibold" style={{ flex: '1 1 auto', wordBreak: 'break-word' }}>
                          {cellValue(c, details)}
                        </span>
                      </li>
                    )
                  })}
                </ul>
              </div>
              <div className="modal-footer p-2 d-flex gap-1 flex-wrap">
                {actions && actions(details)}
                <button type="button" className="btn btn-secondary ms-auto" onClick={() => setDetails(null)}>
                  Fermer
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
