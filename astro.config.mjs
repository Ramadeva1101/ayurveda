// @ts-check
import { defineConfig } from 'astro/config';

import alpinejs from '@astrojs/alpinejs';
import sitemap from '@astrojs/sitemap';

// https://astro.build/config
export default defineConfig({
  site: 'https://ayurvedicwellnesshouse.com',
  prefetch: {
    prefetchAll: true,        // prefetch all internal links automatically
    defaultStrategy: 'hover', // start fetching when user hovers a link
  },
  integrations: [alpinejs(), sitemap()]
});
