// @ts-check
import { defineConfig, sharpImageService } from 'astro/config';

import alpinejs from '@astrojs/alpinejs';
import sitemap from '@astrojs/sitemap';

// https://astro.build/config
export default defineConfig({
  site: 'https://ayurvedicwellnesshouse.com',
  output: 'static',
  // Our Sanctuary was merged into the home page (Location & Stay)
  redirects: {
    '/sanctuary': '/',
    '/program': '/services/ayurvedic-therapy',
    '/testimonials': '/#guest-videos',
    '/contact': '/#contact',
  },
  image: {
    service: sharpImageService(),
  },
  prefetch: {
    prefetchAll: true,        // prefetch all internal links automatically
    defaultStrategy: 'hover', // start fetching when user hovers a link
  },
  integrations: [alpinejs(), sitemap()],
  // Local development only: send /api and /admin to the PHP backend
  // (started with backend/dev/start.cmd). Production serves both from cPanel.
  vite: {
    server: {
      proxy: {
        '/api': 'http://127.0.0.1:8099',
        '/admin': 'http://127.0.0.1:8099',
      },
    },
  },
});

