import { useEffect, useMemo, useRef, useState } from 'react'

/** Input téléphone avec indicatif pays UEMOA + international (ROADMAP #23).
 * Implémentation légère sans dépendance (pas intl-tel-input = 200KB).
 * Liste : BJ, CI, TG, SN, BF, ML, NE, CM, FR, + Autre.
 * Le composant émet toujours un numéro au format E.164 via onChange(e164). */

interface Country { code: string; dial: string; label: string; flag: string }

const COUNTRIES: Country[] = [
  { code: 'BJ', dial: '+229', label: 'Bénin', flag: '🇧🇯' },
  { code: 'TG', dial: '+228', label: 'Togo', flag: '🇹🇬' },
  { code: 'CI', dial: '+225', label: "Côte d'Ivoire", flag: '🇨🇮' },
  { code: 'BF', dial: '+226', label: 'Burkina Faso', flag: '🇧🇫' },
  { code: 'ML', dial: '+223', label: 'Mali', flag: '🇲🇱' },
  { code: 'NE', dial: '+227', label: 'Niger', flag: '🇳🇪' },
  { code: 'SN', dial: '+221', label: 'Sénégal', flag: '🇸🇳' },
  { code: 'CM', dial: '+237', label: 'Cameroun', flag: '🇨🇲' },
  { code: 'FR', dial: '+33',  label: 'France', flag: '🇫🇷' },
  { code: 'OTHER', dial: '+',  label: 'Autre', flag: '🌍' },
]

export default function PhoneInput({
  name = 'tel',
  value: extValue,
  onChange,
  defaultValue = '',
  placeholder = '97 00 00 01',
  className = 'form-control',
  required = false,
}: {
  name?: string
  value?: string
  onChange?: (e164: string) => void
  defaultValue?: string
  placeholder?: string
  className?: string
  required?: boolean
}) {
  const [country, setCountry] = useState<Country>(COUNTRIES[0]) // BJ par défaut
  const [local, setLocal] = useState('')
  const [customDial, setCustomDial] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)

  const dial = country.code === 'OTHER' ? customDial : country.dial

  const e164 = useMemo(() => {
    const digits = local.replace(/\D/g, '')
    if (!digits) return ''
    const d = dial.replace(/\D/g, '')
    return '+' + d + digits
  }, [dial, local])

  // Initialiser à partir de defaultValue/extValue
  useEffect(() => {
    const v = extValue ?? defaultValue ?? ''
    if (!v) return
    const digits = v.replace(/\D/g, '')
    const match = COUNTRIES.find((c) => c.code !== 'OTHER' && digits.startsWith(c.dial.replace(/\D/g, '')))
    if (match) {
      setCountry(match)
      setLocal(digits.slice(match.dial.replace(/\D/g, '').length))
    } else if (digits.startsWith('00')) {
      setLocal(digits)
    } else {
      // Essayer de détecter indicatif sur 3 chiffres après +
      setLocal(digits.startsWith('229') ? digits.slice(3) : digits)
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => { onChange?.(e164) }, [e164, onChange])

  return (
    <div className="input-group">
      <select
        className="form-select"
        style={{ maxWidth: '130px', flexShrink: 0 }}
        value={country.code}
        onChange={(e) => {
          const c = COUNTRIES.find((x) => x.code === e.target.value) || COUNTRIES[0]
          setCountry(c)
        }}
        title="Indicatif pays"
      >
        {COUNTRIES.map((c) => (
          <option key={c.code} value={c.code}>{c.flag} {c.dial}</option>
        ))}
      </select>
      {country.code === 'OTHER' && (
        <input
          type="text"
          className="form-control"
          style={{ maxWidth: '90px' }}
          placeholder="+XXX"
          value={customDial}
          onChange={(e) => setCustomDial(e.target.value.replace(/[^\d+]/g, ''))}
        />
      )}
      <input
        ref={inputRef}
        type="tel"
        name={name}
        className={className}
        placeholder={placeholder}
        value={local}
        required={required}
        autoComplete="tel-national"
        onChange={(e) => setLocal(e.target.value.replace(/[^\d\s.-]/g, ''))}
        onFocus={(e) => e.target.select()}
      />
    </div>
  )
}
