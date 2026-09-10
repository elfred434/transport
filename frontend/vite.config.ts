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
        // Si VITE_API_TARGET finit par '/api' (ex: ngrok pointant directement sur l'API),
        // on le retire pour éviter le double préfixe /api/api/.
        target: (process.env.VITE_API_TARGET || 'http://127.0.0.1:8000').replace(/\/api\/?$/, ''),
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      '/storage': {
        target: (process.env.VITE_API_TARGET || 'http://127.0.0.1:8000').replace(/\/api\/?$/, ''),
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      '/uploads': {
        target: (process.env.VITE_API_TARGET || 'http://127.0.0.1:8000').replace(/\/api\/?$/, ''),
        changeOrigin: true,
      },
    },
  },
})
