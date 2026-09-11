import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// SPA React — frontend de la plateforme de transport.
//
// Le backend Django tourne par défaut sur le port 8000. En dev, `/api`,
// `/storage` et `/uploads` sont proxyfiés vers l'URL définie par
// VITE_API_TARGET (dans .env) ou http://127.0.0.1:8000 si non précisée.
//
// IMPORTANT: dans le fichier vite.config.ts, les variables `VITE_*` du .env
// NE sont PAS chargées automatiquement par Vite — il faut appeler loadEnv()
// manuellement pour que le proxy serveur les voie. Sinon, process.env.*
// reste undefined et on retombe systématiquement sur la valeur par défaut.
//
// Nettoyage auto : liens markdown [url](url), chevrons <url>, guillemets et
// suffixe /api éventuel sont retirés, pour rendre le .env tolérant aux
// copier-coller.
function resolveApiTarget(mode: string): string {
  // Charge toutes les variables VITE_* du .env et les injecte dans process.env
  const env = loadEnv(mode, process.cwd(), '')
  // Copie dans process.env pour les autres endroits qui en auraient besoin
  for (const k in env) process.env[k] = env[k]
  let t = env.VITE_API_TARGET || process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'
  t = t.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
  t = t.replace(/[<>"]/g, '').trim()
  t = t.replace(/\/api\/?$/, '')
  if (!/^https?:\/\//i.test(t)) t = 'http://' + t
  return t
}

export default defineConfig(({ mode }) => {
  const apiTarget = resolveApiTarget(mode)
  // Log visible dans la console Vite au démarrage (très utile au débogage)
  console.log('[vite] proxy /api -> ' + apiTarget)

  return {
    plugins: [react()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      allowedHosts: ['.e2b.app', '.ngrok-free.app', '.ngrok.io', 'localhost'],
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          headers: { 'ngrok-skip-browser-warning': 'true' },
        },
        '/storage': {
          target: apiTarget,
          changeOrigin: true,
          headers: { 'ngrok-skip-browser-warning': 'true' },
        },
        '/uploads': {
          target: apiTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
