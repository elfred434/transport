import { useState } from 'react'

interface PasswordFieldProps {
  name?: string
  label?: string | React.ReactNode | null
  placeholder?: string
  minLength?: number
  required?: boolean
  autoComplete?: string
  value?: string
  onChange?: (v: string) => void
  defaultValue?: string
  /** Référence à assigner sur l'input (pour PasswordGenerator par exemple). */
  inputRef?: React.RefObject<HTMLInputElement | null>
  /** Classe BS ajoutées (ex: "form-control-lg"). */
  className?: string
}

/**
 * Champ mot de passe réutilisable avec bouton afficher/masquer (ROADMAP #26).
 * N'utilise JAMAIS un emoji comme bouton — icône FontAwesome + attribut aria.
 */
export default function PasswordField({
  name = 'password',
  label = 'Mot de passe',
  placeholder,
  minLength,
  required = true,
  autoComplete,
  value,
  onChange,
  defaultValue,
  inputRef,
  className = '',
}: PasswordFieldProps) {
  const [visible, setVisible] = useState(false)

  return (
    <div>
      {label && <label className="form-label">{label}</label>}
      <div className="position-relative">
        <input
          ref={inputRef}
          type={visible ? 'text' : 'password'}
          name={name}
          className={`form-control ${className}`}
          minLength={minLength}
          required={required}
          autoComplete={autoComplete}
          placeholder={placeholder}
          value={value}
          defaultValue={defaultValue}
          onChange={(e) => onChange?.(e.target.value)}
          style={{ paddingRight: '2.5rem' }}
        />
        <button
          type="button"
          className="btn btn-sm btn-link text-secondary position-absolute top-50 end-0 translate-middle-y me-1 px-2"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          title={visible ? 'Masquer' : 'Afficher'}
          style={{ zIndex: 3, textDecoration: 'none' }}
        >
          <i className={`fa-solid ${visible ? 'fa-eye-slash' : 'fa-eye'}`}></i>
        </button>
      </div>
    </div>
  )
}
