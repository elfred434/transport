/**
 * Base de données pays + villes principales.
 *
 * On concentre l'Afrique de l'Ouest et Centrale (marché cible) avec un bon
 * nombre de villes ; pour les autres pays on met la capitale + quelques
 * grandes villes. L'utilisateur peut toujours saisir librement via le champ.
 */

export interface Pays {
  code: string        // code ISO 2 lettres (clé stable)
  nom: string         // nom en français
  drapeau: string     // emoji drapeau
  villes: string[]
}

export const PAYS: Pays[] = [
  { code: 'BJ', nom: 'Bénin', drapeau: '🇧🇯', villes: [
    'Cotonou', 'Porto-Novo', 'Parakou', 'Bohicon', 'Abomey-Calavi',
    'Ouidah', 'Djougou', 'Natitingou', 'Lokossa', 'Malanville',
  ]},
  { code: 'TG', nom: 'Togo', drapeau: '🇹🇬', villes: [
    'Lomé', 'Sokodé', 'Kara', 'Kpalimé', 'Atakpamé', 'Tsévié', 'Dapaong',
  ]},
  { code: 'BF', nom: 'Burkina Faso', drapeau: '🇧🇫', villes: [
    'Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Banfora', 'Ouahigouya',
    'Dédougou', 'Tenkodogo',
  ]},
  { code: 'NE', nom: 'Niger', drapeau: '🇳🇪', villes: [
    'Niamey', 'Zinder', 'Maradi', 'Agadez', 'Tahoua', 'Dosso', 'Tillabéri',
  ]},
  { code: 'NG', nom: 'Nigeria', drapeau: '🇳🇬', villes: [
    'Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt', 'Benin City',
    'Enugu', 'Kaduna', 'Onitsha', 'Calabar', 'Abeokuta', 'Uyo',
  ]},
  { code: 'GH', nom: 'Ghana', drapeau: '🇬🇭', villes: [
    'Accra', 'Kumasi', 'Tamale', 'Sekondi-Takoradi', 'Ashaiman',
    'Tema', 'Obuasi', 'Ho',
  ]},
  { code: 'CI', nom: 'Côte d\'Ivoire', drapeau: '🇨🇮', villes: [
    'Abidjan', 'Yamoussoukro', 'Bouaké', 'Daloa', 'San-Pédro',
    'Korhogo', 'Man', 'Gagnoa',
  ]},
  { code: 'ML', nom: 'Mali', drapeau: '🇲🇱', villes: [
    'Bamako', 'Sikasso', 'Ségou', 'Mopti', 'Tombouctou', 'Kayes', 'Gao',
  ]},
  { code: 'SN', nom: 'Sénégal', drapeau: '🇸🇳', villes: [
    'Dakar', 'Touba', 'Thiès', 'Rufisque', 'Kaolack', 'Saint-Louis',
    'Ziguinchor', 'Mbour',
  ]},
  { code: 'GN', nom: 'Guinée', drapeau: '🇬🇳', villes: [
    'Conakry', 'Kankan', 'Nzérékoré', 'Kindia', 'Labé', 'Guéckédou',
  ]},
  { code: 'CM', nom: 'Cameroun', drapeau: '🇨🇲', villes: [
    'Douala', 'Yaoundé', 'Garoua', 'Bamenda', 'Bafoussam', 'Maroua',
    'Kribi', 'Limbé',
  ]},
  { code: 'GA', nom: 'Gabon', drapeau: '🇬🇦', villes: [
    'Libreville', 'Port-Gentil', 'Franceville', 'Oyem', 'Moanda',
  ]},
  { code: 'CG', nom: 'Congo-Brazzaville', drapeau: '🇨🇬', villes: [
    'Brazzaville', 'Pointe-Noire', 'Dolisie', 'Nkayi', 'Ouesso',
  ]},
  { code: 'CD', nom: 'RD Congo', drapeau: '🇨🇩', villes: [
    'Kinshasa', 'Lubumbashi', 'Goma', 'Bukavu', 'Kisangani', 'Matadi',
    'Mbuji-Mayi', 'Kolwezi',
  ]},
  { code: 'TD', nom: 'Tchad', drapeau: '🇹🇩', villes: [
    'N\'Djaména', 'Moundou', 'Abéché', 'Sarh', 'Kélo',
  ]},
  { code: 'CF', nom: 'Rép. centrafricaine', drapeau: '🇨🇫', villes: [
    'Bangui', 'Bimbo', 'Berbérati', 'Carnot', 'Bambari',
  ]},
  // Hors Afrique (principaux pays de la diaspora / expédition)
  { code: 'FR', nom: 'France', drapeau: '🇫🇷', villes: [
    'Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes',
    'Bordeaux', 'Lille', 'Strasbourg', 'Montpellier',
  ]},
  { code: 'BE', nom: 'Belgique', drapeau: '🇧🇪', villes: [
    'Bruxelles', 'Anvers', 'Gand', 'Charleroi', 'Liège', 'Bruges',
  ]},
  { code: 'DE', nom: 'Allemagne', drapeau: '🇩🇪', villes: [
    'Berlin', 'Hambourg', 'Munich', 'Cologne', 'Francfort', 'Stuttgart',
    'Düsseldorf', 'Brême',
  ]},
  { code: 'IT', nom: 'Italie', drapeau: '🇮🇹', villes: [
    'Rome', 'Milan', 'Naples', 'Turin', 'Florence', 'Bologne', 'Venise',
  ]},
  { code: 'ES', nom: 'Espagne', drapeau: '🇪🇸', villes: [
    'Madrid', 'Barcelone', 'Valence', 'Séville', 'Bilbao', 'Malaga',
  ]},
  { code: 'GB', nom: 'Royaume-Uni', drapeau: '🇬🇧', villes: [
    'Londres', 'Manchester', 'Birmingham', 'Liverpool', 'Leeds', 'Bristol',
  ]},
  { code: 'US', nom: 'États-Unis', drapeau: '🇺🇸', villes: [
    'New York', 'Los Angeles', 'Chicago', 'Houston', 'Atlanta', 'Washington',
    'Miami', 'Dallas', 'Boston', 'San Francisco',
  ]},
  { code: 'CA', nom: 'Canada', drapeau: '🇨🇦', villes: [
    'Montréal', 'Toronto', 'Ottawa', 'Vancouver', 'Calgary', 'Québec',
  ]},
  { code: 'CN', nom: 'Chine', drapeau: '🇨🇳', villes: [
    'Pékin', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Hong Kong', 'Chengdu',
  ]},
  { code: 'AE', nom: 'Émirats arabes unis', drapeau: '🇦🇪', villes: [
    'Dubaï', 'Abou Dabi', 'Charjah', 'Al Aïn',
  ]},
  { code: 'TR', nom: 'Turquie', drapeau: '🇹🇷', villes: [
    'Istanbul', 'Ankara', 'Izmir', 'Bursa', 'Antalya',
  ]},
  { code: 'MA', nom: 'Maroc', drapeau: '🇲🇦', villes: [
    'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir',
  ]},
]

export function trouverPays(nomOuCode?: string | null): Pays | undefined {
  if (!nomOuCode) return undefined
  const s = nomOuCode.trim().toLowerCase()
  if (!s) return undefined
  return PAYS.find(p =>
    p.code.toLowerCase() === s || p.nom.toLowerCase() === s
  )
}

export function villesDuPays(nomOuCode?: string | null): string[] {
  const p = trouverPays(nomOuCode)
  return p ? p.villes : []
}
