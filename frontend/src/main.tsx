import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import 'bootstrap/dist/css/bootstrap.min.css'
import '@fortawesome/fontawesome-free/css/all.min.css'
// Design hérité — chargé EN PREMIER pour que app.css (design system global)
// puisse le surcharger sans avoir à utiliser !important partout.
import './styles/first-design.css'
import './styles/app.css'
import App from './App'
import { AuthProvider } from './context/AuthContext'
import { ToastProvider } from './components/Toasts'
import ErrorBoundary from './components/ErrorBoundary'
import './lib/tableLabels'  // auto data-label sur les tableaux Bootstrap

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <ToastProvider>
        <AuthProvider>
          <ErrorBoundary>
            <App />
          </ErrorBoundary>
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  </StrictMode>,
)
