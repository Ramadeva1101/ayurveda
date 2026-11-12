// Menu data shared by the navbar, footer, service pages and the packages page.
// Wording comes from the client's "webpage 9th Nov" document.

export const WA_NUMBER = '6285926461359';
export const waLink = (text: string) => `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(text)}`;

// Icon names map to the line icons in src/components/MenuIcon.astro
export type MenuItem = { label: string; href: string; icon: string };

export const serviceMenu: MenuItem[] = [
  { label: 'Ayurvedic Consultation', href: '/services/consultation', icon: 'consultation' },
  { label: 'Javanese Energy Healing', href: '/services/javanese-energy-healing', icon: 'energy' },
  { label: 'Ayurvedic Therapy', href: '/services/ayurvedic-therapy', icon: 'therapy' },
  { label: 'Water Purification', href: '/services/water-purification', icon: 'water' },
];

export type Program = MenuItem & { id: string; name: string; days: string };

export const programs: Program[] = [
  { id: 'pancha-karma', name: 'Pancha Karma (Detox)', label: 'Pancha Karma', days: '7, 14, 21 Days', href: '/packages#pancha-karma', icon: 'detox' },
  { id: 'rejuvenation', name: 'Rejuvenation', label: 'Rejuvenation', days: '3, 5, 7 Days', href: '/packages#rejuvenation', icon: 'rejuvenation' },
  { id: 'weight-loss', name: 'Weight Loss', label: 'Weight Loss', days: '10, 14 Days', href: '/packages#weight-loss', icon: 'weight' },
  { id: 'joints-problem', name: 'Joints Problem', label: 'Joints Problem', days: '5, 7, 10 Days', href: '/packages#joints-problem', icon: 'joints' },
  { id: 'stress-management', name: 'Stress Management', label: 'Stress Management', days: '10, 14, 21 Days', href: '/packages#stress-management', icon: 'stress' },
];

export type NavEntry = { label: string; href: string; children?: MenuItem[] };

// Our Local Partner sits right before Book now.
export const navItems: NavEntry[] = [
  { label: 'Location & Stay', href: '/' },
  { label: 'Our Service', href: '/services', children: serviceMenu },
  { label: 'Wellness Package', href: '/packages' },
  { label: 'About', href: '/about' },
  { label: 'FAQ', href: '/faq' },
  { label: 'Our Local Partner', href: '/our-local-partner' },
];
