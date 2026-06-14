# Overview

## 概要

Schemable Validator は、バリデーション制約の多重管理を解決することを目的としています。

バックエンド側を主体として定義したバリデーション制約をもとに、フロントエンド側で同型のスキーマを扱えるように設計されています。

## コンセプト

このライブラリは、Fluent Interface ライクな記述層を提供することで、フレームワーク非依存なかたちで制約を表現し、各実行環境で扱いやすいスキーマ（PHP バリデーター・JSON Schema）に変換します。

```
PHP (SchemaBuilder)
  └─ SV::object()->string('name')->email('email')
        │
        │  toJsonSchema()
        ▼
  JSON Schema (Draft-07)
        │
        ├─ AJV          (直接消費)
        ├─ Zod adapter  (sv(jsonSchema).build())
        └─ Valibot adapter
```

PHP でルールを変更すれば、クライアントは自動的に追従します。並行管理も、定義のズレも生じません。

このライブラリの特徴は、Zod や Valibot といったバリデーションライブラリ向けに JSON Schema をネイティブスキーマへ変換するアダプターを提供することで、PHP で定義した制約の大半をクライアント側で自動的に適用できる点にあります。アダプターのマッピング対象外となるルール（ファイルフィールド・クロスフィールド制約・カスタムフォーマット）は、`.extend()` / `.refine()` などの拡張ポイントで補完できます。
