---
layout: home

hero:
  name: Schemable Validator
  text: PHP constraints → JSON Schema → any JS framework
  tagline: Define validation rules once in PHP. Export to JSON Schema draft 2020-12. Consume from any JavaScript framework without duplication.
  actions:
    - theme: brand
      text: Get Started
      link: /installation
    - theme: alt
      text: SchemaBuilder
      link: /schema-builder

features:
  - title: Single source of truth
    details: Define all constraints with SV::object() in PHP. Server-side validation and JSON Schema output share the same definition - no duplication.
  - title: JSON Schema draft 2020-12
    details: toJson() exports a standards-compliant schema. Any JSON Schema-aware tool - validators, editors, code generators - can consume it.
  - title: Framework-agnostic client library
    details: '@schemable-validator/client provides a TypeScript ROP-based validation pipeline. Works with Zod, React, Vue, or plain JavaScript.'
  - title: Framework integrations
    details: Provides interfaces and helpers for framework-specific integration. WordPress is currently supported, with further integrations such as Laravel planned.
---
