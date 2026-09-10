import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// SPA React — frontend de la plateforme de transport.
//
// Le backend Laravel tourne sur le port 8002. En développement, `/api` et
// `/uploads` sont proxyfiés vers lui : le navigateur n'appelle que l'origine
// du SPA (pas de CORS, URLs d'images relatives fonctionnelles, y compris à
// travers le proxy de prévisualisation).
//
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
        target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      '/uploads': {
        target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
        changeOrigin: true,
        headers: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
    },
  },
})
