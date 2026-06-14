---
layout: home

hero:
  name: Schemable Validator
  text: PHP constraints → JSON Schema → any JS framework
  tagline: バリデーションルールを PHP で一度定義。JSON Schema draft 2020-12 にエクスポート。あらゆる JavaScript フレームワークから重複なく利用できます。
  actions:
    - theme: brand
      text: Get Started
      link: /ja/installation
    - theme: alt
      text: SchemaBuilder
      link: /ja/schema-builder

features:
  - title: Single source of truth
    details: SV::object() で制約を一元定義。サーバー側検証と JSON Schema 出力が同じ定義を共有し、重複がありません。
  - title: JSON Schema draft 2020-12
    details: toJson() が標準準拠のスキーマを出力。JSON Schema 対応のあらゆるツール（バリデーター・エディター・コードジェネレーター）から利用できます。
  - title: Framework-agnostic client library
    details: '@schemable-validator/client は TypeScript ROP ベースのバリデーションパイプラインを提供。Zod・React・Vue・素の JavaScript で動作します。'
  - title: Framework integrations
    details: フレームワーク固有のインターフェースとヘルパーを提供。現在 WordPress に対応しており、Laravel などの追加対応を予定しています。
---
