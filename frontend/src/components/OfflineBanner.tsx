import { useEffect, useState } from 'react'

/** Bannière "Vous êtes hors ligne" qui écoute navigator.onLine. */
export default function OfflineBanner() {
  const [online, setOnline] = useState<boolean>(
    typeof navigator !== 'undefined' ? navigator.onLine : true,
  )
  useEffect(() => {
    const on = () => setOnline(true)
    const off = () => setOnline(false)
    window.addEventListener('online', on)
    window.addEventListener('offline', off)
    return () => {
      window.removeEventListener('online', on)
      window.removeEventListener('offline', off)
    }
  }, [])
  if (online) return null
  return (
    <div
      role="alert"
      style={{
        position: 'fixed',
        top: 0, left: 0, right: 0,
        zIndex: 2000,
        background: '#dc3545',
        color: '#fff',
        textAlign: 'center',
        padding: '8px 12px',
        fontSize: '0.9rem',
        fontWeight: 600,
      }}
    >
      <i className="fa-solid fa-wifi-slash me-2"></i>
      Vous êtes hors ligne. Les données affichées peuvent ne pas être à jour.
    </div>
  )
}
