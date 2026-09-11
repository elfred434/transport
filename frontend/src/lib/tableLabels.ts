/* ------------------------------------------------------------------
 * Initialisation globale : ajoute automatiquement data-label="<th>"
 * sur chaque <td> des <table class="table"> non gérées par ResponsiveTable.
 * S'exécute au DOMContentLoaded, puis un MutationObserver rattrape les
 * tables rendues plus tard par React.
 * ------------------------------------------------------------------ */
function applyDataLabels(root: ParentNode = document) {
  const tables = root.querySelectorAll<HTMLTableElement>(
    'table.table:not(.rtable-ignore):not([data-labeled])',
  )
  tables.forEach((table) => {
    // Ne pas toucher aux tables de ResponsiveTable
    if (table.closest('.rtable-desktop') || table.closest('.rtable-mobile')) return
    // Collecte les labels d'en-tête
    const headers: string[] = []
    const ths = table.querySelectorAll('thead th')
    ths.forEach((th, i) => { headers[i] = (th.textContent || '').trim().replace(/\s+/g, ' ') })
    // Si pas de thead, pas de label à propager
    if (headers.length === 0) return
    const rows = table.querySelectorAll('tbody tr')
    rows.forEach((tr) => {
      const cells = tr.querySelectorAll('td')
      cells.forEach((td, i) => {
        if (!td.getAttribute('data-label') && headers[i]) {
          td.setAttribute('data-label', headers[i])
        }
      })
    })
    table.setAttribute('data-labeled', '1')
  })
}

function boot() {
  applyDataLabels()
  const mo = new MutationObserver((muts) => {
    muts.forEach((m) => {
      m.addedNodes.forEach((n) => {
        if (n instanceof HTMLElement) applyDataLabels(n)
      })
    })
  })
  mo.observe(document.body, { childList: true, subtree: true })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot)
} else {
  boot()
}
