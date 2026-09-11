import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// SPA React — frontend de la plateforme de transport.
//
// Le backend Django tourne sur le port 8000. En développement, `/api` et
// `/storage` sont proxyfiés vers lui : le navigateur n'appelle que l'origine
// du SPA (pas de CORS, URLs relatives fonctionnelles, y compris à travers ngrok).
//
// Pour changer ponctuellement: VITE_API_TARGET=http://autre:port npm run dev
// Support ngrok : allowedHosts + header ngrok-skip-browser-warning
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    // Prévisualisation sandbox + ngrok : accepter les hôtes proxyés
    allowedHosts: ['.e2b.app', '.ngrok-free.app', '.ngrok.io', 'localhost'],
    proxy: {
      '/api': {
        // Ordre de priorité pour la cible :
        // 1) variable d'environnement VITE_API_TARGET (si définie)
        // 2) sinon http://127.0.0.1:8000 (Django par défaut)
        // On nettoie automatiquement : supprime /api en fin, supprime les
        // crochets/paranthèses qui ont pu être copiés-collés par erreur depuis
        // un rendu markdown (ex: "[http://h](http://h)").
        target: (() => {
          let t = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'
          t = t.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')   // retire les liens markdown
          t = t.replace(/[<>"]/g, '').trim()              // retire chevrons/guillemets
          t = t.replace(/\/api\/?$/, '')                  // retire le suffixe /api
          return t
        })(),
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      '/storage': {
        target: (() => {
          let t = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'
          t = t.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
          t = t.replace(/[<>"]/g, '').trim()
          t = t.replace(/\/api\/?$/, '')
          return t
        })(),
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      '/uploads': {
        target: (() => {
          let t = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'
          t = t.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
          t = t.replace(/[<>"]/g, '').trim()
          t = t.replace(/\/api\/?$/, '')
          return t
        })(),
        changeOrigin: true,
      },
    },
  },
})
