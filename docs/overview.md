# Overview

Schemable Validator is designed to eliminate the duplication of validation constraints across stacks.

Constraints are defined in PHP as the single source of truth. The client side consumes an equivalent schema derived from that definition, keeping both layers in sync without redundant maintenance.

## Concept

The library provides a Fluent Interface layer that expresses constraints in a framework-agnostic form and converts them into the schema format suited to each runtime — a PHP `Validator` on the server side, and JSON Schema for the client.

```
PHP (SchemaBuilder)
  └─ SV::object()->string('name')->email('email')
        │
        │  toJsonSchema()
        ▼
  JSON Schema (draft 2020-12)
        │
        ├─ AJV          (direct consumption)
        ├─ Zod adapter  (sv(jsonSchema).build())
        └─ Valibot adapter
```

When a rule changes in PHP, the client picks it up automatically — no parallel maintenance, no drift.

A key feature of this library is the built-in adapters that convert JSON Schema into native Zod or Valibot schemas, automatically applying the PHP-defined constraints on the client side. Rules that fall outside the adapter's mapping scope — file fields, cross-field constraints, and custom formats — can be filled in through extension points such as `.extend()` and `.refine()`.
