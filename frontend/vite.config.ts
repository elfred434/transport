import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// SPA React — frontend de la plateforme de transport.
//
// Le backend Laravel tourne sur le port 8002. En développement, `/api` et
// `/uploads` sont proxyfiés vers lui : le navigateur n'appelle que l'origine
// du SPA (pas de CORS, URLs d'images relatives fonctionnelles, y compris à
// travers le proxy de prévisualisation).
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 8003,
    // Prévisualisation sandbox : accepter l'hôte proxyé (*.e2b.app).
    allowedHosts: ['.e2b.app'],
    proxy: {
      '/api': {
        target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
        changeOrigin: true,
      },
      '/uploads': {
        target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
        changeOrigin: true,
      },
    },
  },
})
