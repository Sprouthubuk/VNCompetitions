// @ts-check
import { defineConfig } from 'astro/config';
import node from '@astrojs/node';

// Two build modes:
// - Production (Forge): server-rendered with the Node adapter, reading live data from the Laravel API (API_URL).
// - PUBLIC_PREVIEW=1: a flat, self-contained static site with relative links and inlined CSS,
//   used for the claude.ai design preview. Uses the sample data in src/data/comps.ts.
const preview = process.env.PUBLIC_PREVIEW === '1';

export default defineConfig({
  site: process.env.SITE_URL || 'https://vaultninecompetitions.co.uk',
  output: preview ? 'static' : 'server',
  adapter: preview ? undefined : node({ mode: 'standalone' }),
  build: {
    format: preview ? 'file' : 'directory',
    inlineStylesheets: preview ? 'always' : 'auto',
  },
  devToolbar: { enabled: false },
});
