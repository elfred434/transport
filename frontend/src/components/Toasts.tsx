import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from 'react'

/**
 * Notifications flottantes — port de UI.toast().
 * Mêmes classes CSS (toast-item / toast-success / toast-error / toast-info)
 * et même temporisation (4 s + fondu).
 */

type ToastType = 'success' | 'error' | 'info'

interface Toast {
  id: number
  message: ReactNode
  type: ToastType
  hide: boolean
}

interface ToastContextValue {
  toast: (message: ReactNode, type?: ToastType) => void
}

const ToastContext = createContext<ToastContextValue>({ toast: () => {} })

export function useToast(): ToastContextValue {
  return useContext(ToastContext)
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const nextId = useRef(1)

  const toast = useCallback((message: ReactNode, type: ToastType = 'success') => {
    const id = nextId.current++
    setToasts((prev) => [...prev, { id, message, type, hide: false }])

    setTimeout(() => {
      setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, hide: true } : t)))
      setTimeout(() => {
        setToasts((prev) => prev.filter((t) => t.id !== id))
      }, 400)
    }, 4000)
  }, [])

  const cls = (type: ToastType) =>
    'toast-item ' + (type === 'error' ? 'toast-error' : type === 'info' ? 'toast-info' : 'toast-success')

  return (
    <ToastContext.Provider value={{ toast }}>
      {children}
      <div id="toast-zone">
        {toasts.map((t) => (
          <div key={t.id} className={cls(t.type) + (t.hide ? ' hide' : '')}>
            {t.message}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}
