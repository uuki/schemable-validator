import { defineConfig } from 'vitepress'

const sidebarEn = [
  {
    text: 'Getting Started',
    items: [
      { text: 'Overview',     link: '/overview' },
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
      { text: 'Client',              link: '/client-adapter' },
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
    text: 'Client API Reference',
    collapsed: false,
    items: [
      { text: 'Index',               link: '/client-api' },
      { text: 'Core Validator',      link: '/client-reference/core-validator' },
      { text: 'Result Primitives',   link: '/client-reference/result' },
      { text: 'Constraint Pipeline', link: '/client-reference/constraint-pipeline' },
      { text: 'Zod Adapter',         link: '/client-reference/zod-adapter' },
      { text: 'Valibot Adapter',     link: '/client-reference/valibot-adapter' },
      { text: 'Types',               link: '/client-reference/types' },
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
]

const sidebarJa = [
  {
    text: 'はじめに',
    items: [
      { text: '概要',          link: '/ja/overview' },
      { text: 'インストール', link: '/ja/installation' },
    ],
  },
  {
    text: 'ガイド',
    items: [
      { text: '機能ガイド',            link: '/ja/feature-guide' },
      { text: 'インターフェース',      link: '/ja/interfaces' },
      { text: 'SchemaBuilder',      link: '/ja/schema-builder' },
      { text: 'カスタムバリデーション', link: '/ja/custom-validation' },
      { text: 'MessageDict (i18n)', link: '/ja/message-dict' },
      { text: 'クライアント',          link: '/ja/client-adapter' },
    ],
  },
  {
    text: 'サンプル',
    items: [
      { text: 'Core',      link: '/ja/examples/core' },
      { text: 'WordPress', link: '/ja/examples/wordpress' },
      { text: 'Client',    link: '/ja/examples/client' },
    ],
  },
  {
    text: 'スキーマリファレンス',
    collapsed: false,
    items: [
      { text: 'Index',            link: '/ja/reference/' },
      { text: 'String',           link: '/ja/reference/string' },
      { text: 'Integer / Number', link: '/ja/reference/number' },
      { text: 'Boolean / Enum',   link: '/ja/reference/scalar' },
      { text: 'Modifiers',        link: '/ja/reference/modifiers' },
      { text: 'Array',            link: '/ja/reference/array' },
      { text: 'File / Respect',   link: '/ja/reference/extended' },
      { text: 'Object & Output',  link: '/ja/reference/object' },
    ],
  },
  {
    text: 'クライアント API リファレンス',
    collapsed: false,
    items: [
      { text: 'Index',               link: '/ja/client-api' },
      { text: 'コアバリデーター',      link: '/ja/client-reference/core-validator' },
      { text: 'Result プリミティブ',  link: '/ja/client-reference/result' },
      { text: 'Constraint パイプライン', link: '/ja/client-reference/constraint-pipeline' },
      { text: 'Zod アダプター',       link: '/ja/client-reference/zod-adapter' },
      { text: 'Valibot アダプター',   link: '/ja/client-reference/valibot-adapter' },
      { text: '型リファレンス',        link: '/ja/client-reference/types' },
    ],
  },
  {
    text: 'マイグレーション',
    items: [
      { text: '移行ガイド', link: '/ja/removal-guide' },
    ],
  },
  {
    text: '開発',
    items: [
      { text: '開発ガイド', link: '/ja/development' },
    ],
  },
]

export default defineConfig({
  title: 'Schemable Validator',
  description: 'Define validation constraints once in PHP — export to JSON Schema and consume from any JavaScript framework.',

  base: '/schemable-validator/',
  srcDir: '../../docs',
  outDir: './.vitepress/dist',

  locales: {
    root: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        nav: [
          { text: 'Guide', link: '/installation' },
          { text: 'GitHub', link: 'https://github.com/uuki/schemable-validator' },
        ],
        sidebar: sidebarEn,
        outline: { level: [2, 3], label: 'On this page' },
      },
    },
    ja: {
      label: '日本語',
      lang: 'ja',
      themeConfig: {
        nav: [
          { text: 'ガイド', link: '/ja/installation' },
          { text: 'GitHub', link: 'https://github.com/uuki/schemable-validator' },
        ],
        sidebar: sidebarJa,
        outline: { level: [2, 3], label: '目次' },
      },
    },
  },

  themeConfig: {
    search: { provider: 'local' },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/uuki/schemable-validator' },
    ],
  },
})
