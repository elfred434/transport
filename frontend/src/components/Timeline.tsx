/**
 * Timeline verticale responsive (ROADMAP #35).
 * - Pas de couleur bleue seule : l'indicateur utilise un cercle + icône + texte.
 * - Mobile-first : se réduit bien sous sm.
 */
import { datetime } from '../lib/format'

export interface TimelineStep {
  statut: string
  date_etape?: string | null
  commentaire?: string | null
  icon?: string | null
}

const ICONS: Record<string, string> = {
  'En attente': 'fa-clock',
  'Payé': 'fa-credit-card',
  'En cours': 'fa-truck',
  'Livré': 'fa-circle-check',
  'Annulé': 'fa-xmark',
  'Remboursé': 'fa-rotate-left',
}

const COLORS: Record<string, string> = {
  'En attente': '#6c757d',       // gris
  'Payé': '#198754',            // vert
  'En cours': '#0d6efd',        // bleu — ok ici accompagné d'icône+texte
  'Livré': '#198754',           // vert
  'Annulé': '#dc3545',          // rouge
  'Remboursé': '#6c757d',       // gris
}

function statutColor(s: string) {
  return COLORS[s] || '#6c757d'
}
function statutIcon(s: string) {
  return ICONS[s] || 'fa-circle'
}

export default function Timeline({ steps }: { steps: TimelineStep[] }) {
  if (!steps || steps.length === 0) {
    return <p className="text-muted small mb-0">Aucune étape enregistrée pour le moment.</p>
  }

  return (
    <div className="position-relative" style={{ paddingLeft: '2rem' }}>
      {/* Trait vertical */}
      <div
        className="position-absolute"
        style={{
          left: '0.75rem',
          top: '0.5rem',
          bottom: '0.5rem',
          width: '2px',
          background: '#dee2e6',
        }}
      />
      <ul className="list-unstyled mb-0">
        {steps.map((s, i) => {
          const color = statutColor(s.statut)
          const icon = s.icon || statutIcon(s.statut)
          const last = i === steps.length - 1
          return (
            <li
              key={i}
              className={`position-relative ${last ? '' : 'pb-4'}`}
            >
              {/* Cercle */}
              <span
                className="position-absolute d-flex align-items-center justify-content-center rounded-circle text-white"
                style={{
                  left: '-2rem',
                  top: '0',
                  width: '1.75rem',
                  height: '1.75rem',
                  background: color,
                  fontSize: '0.8rem',
                  boxShadow: '0 0 0 3px #fff',
                }}
                aria-hidden="true"
              >
                <i className={`fa-solid ${icon}`} />
              </span>
              <div className="ps-2">
                <div className="fw-semibold" style={{ color }}>{s.statut}</div>
                {s.commentaire && (
                  <div className="small text-muted">{s.commentaire}</div>
                )}
                {s.date_etape && (
                  <div className="small text-muted">
                    <i className="fa-regular fa-clock me-1" />{datetime(s.date_etape)}
                  </div>
                )}
              </div>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
