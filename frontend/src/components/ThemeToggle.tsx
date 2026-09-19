/** Bouton lune/soleil pour basculer dark mode (ROADMAP #37). */
import { useTheme } from '../context/ThemeContext'

export default function ThemeToggle() {
  const { theme, toggle } = useTheme()
  const isDark = theme === 'dark'
  return (
    <button
      type="button"
      className="btn btn-sm btn-outline-secondary"
      onClick={toggle}
      aria-label={isDark ? 'Activer le mode clair' : 'Activer le mode sombre'}
      title={isDark ? 'Mode clair' : 'Mode sombre'}
    >
      <i className={`fa-solid ${isDark ? 'fa-sun' : 'fa-moon'}`} />
    </button>
  )
}
