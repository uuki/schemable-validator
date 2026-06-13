import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Schemable Validator',
  description: 'Define validation constraints once in PHP — export to JSON Schema and consume from any JavaScript framework.',

  srcDir: '../../docs',
  outDir: './.vitepress/dist',

  themeConfig: {
    search: { provider: 'local' },

    nav: [
      { text: 'Guide', link: '/01-installation' },
      { text: 'GitHub', link: 'https://github.com/uuki/schemable-validator' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/01-installation' },
        ],
      },
      {
        text: 'Guide',
        items: [
          { text: 'Feature Guide',      link: '/02-feature-guide' },
          { text: 'Interfaces',         link: '/03-interfaces' },
          { text: 'SchemaBuilder',      link: '/05-schema-builder' },
          { text: 'Custom Validation',  link: '/06-custom-validation' },
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
          { text: 'Development', link: '/04-development' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/uuki/schemable-validator' },
    ],
  },
})
