/**
 * Carte OpenStreetMap (ROADMAP #27) + autocomplétion Nominatim (ROADMAP #28).
 *
 * Utilise Leaflet directement (pas react-leaflet, évite les conflits React 19).
 * - Pick marker : cliquer sur la carte pour choisir un point de départ / destination
 * - Search box : autocomplétion Nominatim (openstreetmap.org)
 * - Reverse geocoding : clic → met à jour le champ adresse
 * - Géolocalisation navigateur (si autorisée)
 *
 * IMPORTANT: la carte n'affiche JAMAIS un fond bleu uni en indicateur unique :
 * les marqueurs ont un icône personnalisé, un popup, et les contrôles zoom.
 */
import { useEffect, useRef, useState } from 'react'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix des icônes Leaflet (marqueurs cassés sans ce fix avec bundlers)
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

// @ts-ignore
delete (L.Icon.Default.prototype)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl,
  iconUrl,
  shadowUrl,
})

interface LatLng { lat: number; lng: number }
interface NominatimResult {
  place_id: number
  display_name: string
  lat: string
  lon: string
  address?: Record<string, string>
}

interface MapPickerProps {
  /** Position initiale (défaut Bénin centre). */
  initial?: LatLng
  initialZoom?: number
  height?: string | number
  /** Appelé quand un lieu est choisi (clic carte ou résultat recherche). */
  onPick: (p: { lat: number; lng: number; address: string }) => void
  /** Adresse contrôlée depuis l'extérieur (pré-remplissage). */
  address?: string
  /** Label affiché au-dessus de la recherche. */
  label?: string
  /** Icône FontAwesome du marqueur. */
  markerIcon?: string
}

const DEFAULT_CENTER: LatLng = { lat: 8.0, lng: 2.3 } // Bénin centre

export default function MapPicker({
  initial,
  initialZoom = 6,
  height = 300,
  onPick,
  address = '',
  label = 'Choisir un point sur la carte',
  markerIcon = 'fa-location-dot',
}: MapPickerProps) {
  const mapEl = useRef<HTMLDivElement>(null)
  const mapRef = useRef<L.Map | null>(null)
  const markerRef = useRef<L.Marker | null>(null)
  const [query, setQuery] = useState(address)
  const [results, setResults] = useState<NominatimResult[]>([])
  const [loading, setLoading] = useState(false)
  const [userLocating, setUserLocating] = useState(false)

  // Initialisation carte
  useEffect(() => {
    if (!mapEl.current || mapRef.current) return
    const map = L.map(mapEl.current, {
      center: [initial?.lat ?? DEFAULT_CENTER.lat, initial?.lng ?? DEFAULT_CENTER.lng],
      zoom: initialZoom,
      zoomControl: true,
    })
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map)
    mapRef.current = map

    const onClick = async (e: L.LeafletMouseEvent) => {
      const { lat, lng } = e.latlng
      placeMarker(lat, lng)
      // Reverse geocoding
      try {
        const r = await fetch(
          `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fr`
        )
        const d = await r.json()
        const addr = d.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`
        setQuery(addr)
        onPick({ lat, lng, address: addr })
      } catch {
        const addr = `${lat.toFixed(5)}, ${lng.toFixed(5)}`
        setQuery(addr)
        onPick({ lat, lng, address: addr })
      }
    }
    map.on('click', onClick)

    // Si une position initiale est fournie, on pose le marqueur
    if (initial) placeMarker(initial.lat, initial.lng)

    return () => {
      map.off('click', onClick)
      map.remove()
      mapRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function placeMarker(lat: number, lng: number) {
    if (!mapRef.current) return
    if (markerRef.current) markerRef.current.setLatLng([lat, lng])
    else {
      const customIcon = L.divIcon({
        className: 'custom-marker',
        html: `<div style="font-size:24px;color:#dc2626;filter:drop-shadow(0 1px 2px rgba(0,0,0,.3));"><i class="fa-solid ${markerIcon}"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 20],
      })
      markerRef.current = L.marker([lat, lng], { icon: customIcon }).addTo(mapRef.current)
    }
    mapRef.current.setView([lat, lng], Math.max(mapRef.current.getZoom(), 13))
  }

  // Autocomplétion Nominatim (debounce 500ms)
  useEffect(() => {
    if (!query || query.length < 3) { setResults([]); return }
    const t = setTimeout(async () => {
      setLoading(true)
      try {
        const r = await fetch(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&accept-language=fr&countrycodes=bj,tg,ci,bf,ml,ne,sn,cm,fr,ne,ng,gh`
        )
        const data: NominatimResult[] = await r.json()
        setResults(data)
      } catch { setResults([]) }
      setLoading(false)
    }, 500)
    return () => clearTimeout(t)
  }, [query])

  const selectResult = (r: NominatimResult) => {
    const lat = parseFloat(r.lat), lng = parseFloat(r.lon)
    placeMarker(lat, lng)
    setQuery(r.display_name)
    setResults([])
    onPick({ lat, lng, address: r.display_name })
  }

  const geolocate = () => {
    if (!navigator.geolocation || !mapRef.current) return
    setUserLocating(true)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords.latitude, lng = pos.coords.longitude
        placeMarker(lat, lng)
        // Reverse
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fr`)
          .then(r => r.json()).then(d => {
            const addr = d.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`
            setQuery(addr)
            onPick({ lat, lng, address: addr })
          }).catch(() => {
            const addr = `${lat.toFixed(5)}, ${lng.toFixed(5)}`
            setQuery(addr); onPick({ lat, lng, address: addr })
          }).finally(() => setUserLocating(false))
      },
      () => setUserLocating(false),
      { timeout: 8000 }
    )
  }

  return (
    <div>
      <label className="form-label fw-semibold">{label}</label>
      <div className="position-relative mb-2">
        <div className="input-group">
          <span className="input-group-text"><i className="fa-solid fa-magnifying-glass" /></span>
          <input
            type="text"
            className="form-control"
            placeholder="Rechercher une adresse, ville…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            autoComplete="off"
          />
          <button type="button" className="btn btn-outline-secondary" onClick={geolocate} disabled={userLocating} title="Me géolocaliser">
            <i className={`fa-solid fa-location-crosshairs ${userLocating ? 'fa-spin' : ''}`} />
          </button>
        </div>
        {results.length > 0 && (
          <ul className="list-group position-absolute w-100 shadow-sm" style={{ zIndex: 1000, maxHeight: 240, overflowY: 'auto' }}>
            {results.map(r => (
              <li key={r.place_id} className="list-group-item list-group-item-action" onClick={() => selectResult(r)} role="button">
                <i className="fa-solid fa-location-dot text-danger me-2" />
                <small>{r.display_name}</small>
              </li>
            ))}
          </ul>
        )}
        {loading && <small className="text-muted"><i className="fa-solid fa-spinner fa-spin me-1" /> Recherche…</small>}
      </div>
      <div
        ref={mapEl}
        style={{
          height: typeof height === 'number' ? `${height}px` : height,
          width: '100%',
          borderRadius: 8,
          border: '1px solid #dee2e6',
          zIndex: 1,
        }}
      />
      <small className="text-muted d-block mt-1">Clique sur la carte pour affiner la position.</small>
    </div>
  )
}
