import { Component, type ErrorInfo, type ReactNode } from 'react'

/* ------------------------------------------------------------------ */
/*  ErrorBoundary : attrape les erreurs React et affiche un message   */
/*  lisible au lieu d'une page blanche. Pratique quand une page      */
/*  crash à cause d'un ReferenceError / TypeError en rendu.           */
/* ------------------------------------------------------------------ */

interface Props { children: ReactNode }
interface State { error: Error | null; info: string }

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null, info: '' }

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('[ErrorBoundary]', error, info)
    this.setState({ info: info.componentStack || '' })
  }

  reset = () => this.setState({ error: null, info: '' })

  render() {
    if (this.state.error) {
      return (
        <div className="page-card" style={{ maxWidth: 720, margin: '24px auto' }}>
          <h4 className="text-danger">
            <i className="fa-solid fa-triangle-exclamation me-2" />
            Une erreur est survenue sur cette page
          </h4>
          <p className="text-muted small mb-2">
            Copiez cette erreur et envoyez-la au développeur :
          </p>
          <pre className="p-3 bg-light rounded small" style={{ overflowX: 'auto', whiteSpace: 'pre-wrap', color: '#b91c1c', fontSize: '0.8rem' }}>
{this.state.error.name}: {this.state.error.message}
{this.state.error.stack?.split('\n').slice(0, 8).join('\n')}
{this.state.info && '\n--- componentStack ---\n' + this.state.info.split('\n').slice(0, 6).join('\n')}
          </pre>
          <div className="d-flex gap-2 flex-wrap mt-3">
            <button className="btn btn-primary" onClick={this.reset}>
              <i className="fa-solid fa-rotate" /> Réessayer
            </button>
            <button className="btn btn-outline-secondary" onClick={() => window.location.reload()}>
              Recharger la page
            </button>
            <a href="/dashboard" className="btn btn-outline-primary">
              <i className="fa-solid fa-house" /> Accueil
            </a>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
