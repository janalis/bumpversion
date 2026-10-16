import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Bumpversion',
  description: 'Version your project and prepare releases with a PHP CLI.',
  lang: 'en-US',
  base: '/bumpversion/',
  cleanUrls: true,
  head: [['link', { rel: 'icon', type: 'image/svg+xml', href: '/bumpversion/favicon.svg' }]],
  sitemap: { hostname: 'https://janalis.github.io/bumpversion/' },
  themeConfig: {
    logo: '/favicon.svg',
    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'CLI reference', link: '/configuration' },
      { text: 'Contributing', link: '/contributing' },
    ],
    sidebar: [
      { text: 'Get started', items: [
        { text: 'Quick start', link: '/getting-started' },
        { text: 'Installation', link: '/installation' },
      ] },
      { text: 'Use Bumpversion', items: [
        { text: 'Configuration and CLI', link: '/configuration' },
        { text: 'Changelogs and SemVer', link: '/changelogs' },
        { text: 'Git releases', link: '/releases' },
        { text: 'Migration', link: '/migration' },
      ] },
      { text: 'Project', items: [
        { text: 'Contributing', link: '/contributing' },
        { text: 'Security', link: '/security' },
      ] },
    ],
    search: { provider: 'local' },
    socialLinks: [{ icon: 'github', link: 'https://github.com/janalis/bumpversion' }],
    footer: { message: 'Released under the MIT license.' },
  },
})
