# TODO — Client Data Required Before Launch

All items below are clearly marked with `<!-- TODO -->` comments in the source code. Search for `TODO` in the codebase to find each one.

---

## Contact Information

### WhatsApp Business Number
**Files:** `src/pages/contact.astro`, `src/components/FloatingWhatsApp.astro`

Replace the placeholder number in both files:
- Contact page button: `https://wa.me/62XXXXXXXXXX?text=Hello%2C%20I%27d%20like%20to%20ask%20about%20your%20treatments`
- Floating WhatsApp component: `phoneNumber = '6281234567890'`

### Phone / General Contact Number
**File:** `src/layouts/BaseLayout.astro` (JSON-LD structured data)

```js
telephone: '+62XXXXXXXXXX'
```

---

## Location

### Full Address
**Files:** `src/layouts/BaseLayout.astro` (JSON-LD), `src/pages/contact.astro`

JSON-LD:
```js
address: {
  addressLocality: 'Bali', // update city/region
  addressCountry: 'ID'
}
```

Contact page — replace:
```
Meet at the co-work space above the office.
Bali, Indonesia.
```

### Google Maps Embed
**File:** `src/pages/contact.astro`

Replace the `iframe src` with the real location embed URL from Google Maps:
```
https://www.google.com/maps/embed?pb=!1m18!...
```

---

## Operating Hours

**Files:** `src/layouts/BaseLayout.astro` (JSON-LD), `src/pages/contact.astro`

JSON-LD:
```js
openingHours: ['Mo-Sa 09:00-18:00']
```

Contact page:
```
Mon–Sat, 9:00 AM–6:00 PM
Closed Sundays and public holidays
```

---

## Social Media

### Instagram
**Files:** `src/pages/contact.astro`

Replace the placeholder handle:
```html
<a href="https://instagram.com/@yourinstagramhandle" ...>
```
Also update the `@astrojs/sitemap` or sitemap config if applicable.

---

## Form Endpoint

### Email / Form Handler
**File:** `src/pages/contact.astro`

Sign up for a free form-to-email service:
- **Web3Forms** (https://web3forms.com) — free up to 250 submissions/month
- **Formspree** (https://formspree.io) — free up to 50 submissions/month

Once registered, replace the form `action` attribute with your endpoint URL. The form currently submits to a placeholder — validation (Alpine.js) works but the email delivery needs a real endpoint.

---

## Wellness Packages — Pricing & Details

**File:** `src/pages/packages.astro`

Each package card has this TODO comment:
```html
<!-- TODO: confirm included treatments, duration, and pricing with the client -->
```

For each of the 3 packages, confirm and replace:
1. **Signature Ritual** — included treatments, session length
2. **Nusantara-Ayurveda Fusion** — included treatments, session length
3. **Deep Restore Program** — included treatments, number of sessions, program length

All three currently show `Contact us for pricing` as the price placeholder.

---

## Photography — Replace All Placeholders

**Directory:** `public/images/` — see `public/images/README.md` for full list.

Priority order:
1. **Logo** — `logo.png` + `logo.svg` (from client's real brand assets)
2. **Hero images** — `hero-desktop.webp`, `hero-mobile.webp`
3. **Dr. Komang portrait** — `dr-komang-portrait.webp` (about page, 4:5 portrait)
4. **Treatment photos** — 19 images for the program page (see README for full list)
5. **Package images** — 3 images for packages page
6. **OG social images** — `og-default.jpg`, `og-about.jpg`, `og-program.jpg`

All placeholder images use the correct aspect ratios — no layout changes needed when real photos are dropped in.

---

## JSON-LD Structured Data

**File:** `src/layouts/BaseLayout.astro`

Update all TODO fields in the `jsonLd` object before deployment:
- `address` — full postal address
- `telephone` — real phone number
- `openingHours` — actual operating hours
- `sameAs` — Instagram URL once confirmed

---

## Optional: Favicon / Apple Touch Icon

**Files:** `public/favicon.svg`, `public/apple-touch-icon.png`

If the client has brand favicon assets, replace the placeholder lotus SVG with the real favicon.

---

## Optional: apple-touch-icon.png

**File:** `public/apple-touch-icon.png`

Create a 180×180 PNG version of the logo for Apple device home-screen bookmarks.
