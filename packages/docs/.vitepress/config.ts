import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Schemable Validator',
  description: 'Define validation constraints once in PHP — export to JSON Schema and consume from any JavaScript framework.',

  base: '/schemable-validator/',
  srcDir: '../../docs',
  outDir: './.vitepress/dist',

  themeConfig: {
    search: { provider: 'local' },
    outline: { level: [2, 3], label: '目次' },

    nav: [
      { text: 'Guide', link: '/installation' },
      { text: 'GitHub', link: 'https://github.com/uuki/schemable-validator' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/installation' },
        ],
      },
      {
        text: 'Guide',
        items: [
          { text: 'Feature Guide',      link: '/feature-guide' },
          { text: 'Interfaces',         link: '/interfaces' },
          { text: 'SchemaBuilder',      link: '/schema-builder' },
          { text: 'Custom Validation',  link: '/custom-validation' },
          { text: 'MessageDict (i18n)', link: '/message-dict' },
        ],
      },
      {
        text: 'Examples',
        items: [
          { text: 'Core',      link: '/examples/core' },
          { text: 'WordPress', link: '/examples/wordpress' },
          { text: 'Client',    link: '/examples/client' },
        ],
      },
      {
        text: 'Schema Reference',
        collapsed: false,
        items: [
          { text: 'Index',            link: '/reference/' },
          { text: 'String',           link: '/reference/string' },
          { text: 'Integer / Number', link: '/reference/number' },
          { text: 'Boolean / Enum',   link: '/reference/scalar' },
          { text: 'Modifiers',        link: '/reference/modifiers' },
          { text: 'Array',            link: '/reference/array' },
          { text: 'File / Respect',   link: '/reference/extended' },
          { text: 'Object & Output',  link: '/reference/object' },
        ],
      },
      {
        text: 'Migration',
        items: [
          { text: 'Removal Guide', link: '/removal-guide' },
        ],
      },
      {
        text: 'Contributing',
        items: [
          { text: 'Development', link: '/development' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/uuki/schemable-validator' },
    ],
  },
})
