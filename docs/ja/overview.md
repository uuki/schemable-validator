# Overview

Schemable Validator は、外部依存なしで動作する PHP フォームバリデーションライブラリです。
フルーエント API で制約を一度定義すれば、サーバーサイドでそのまま検証できます。
同じ定義から JSON Schema を出力し、クライアントサイドでも利用できます。

## 特徴

- **外部依存なしのサーバーサイド検証。**
  既定のエンジン（`NativeAdapter`）は PHP 7.4 だけで動作します。
  `$_POST` の文字列値は、組み込みの Coercion Contract により宣言された型（integer、number、boolean）に自動変換されるため、フォーム送信時の手動キャストは不要です。

- **必要に応じたクライアントサイド同期。**
  同じスキーマで `toJson()` を呼べば JSON Schema（draft 2020-12）を出力できます。
  組み込みの Zod、Valibot アダプターがネイティブスキーマに変換し、AJV はそのまま消費できます。
  PHP 側でルールを変更すれば、クライアントは自動的に追従します。

- **横断的関心事のドライバ注入。**
  CAPTCHA 検証（reCAPTCHA v3、hCaptcha、Cloudflare Turnstile）、ファイルアップロード検証、画像制約チェック、CSRF 保護はドライバインターフェースで注入します。
  プロバイダの切り替えは設定1行で完了します。

## アーキテクチャ

```
PHP (SchemaBuilder)
  └─ SV::object([ 'name' => SV::string()->min(1), ... ])
        │
        ├─ toValidator()          → サーバーサイド検証 (NativeAdapter, 依存ゼロ)
        │
        └─ toJson() / toJsonSchema()
              │
              JSON Schema (draft 2020-12)
              │
              ├─ AJV          (直接消費)
              ├─ Zod adapter  (sv(jsonSchema).build())
              └─ Valibot adapter
```

検証エンジンは差し替え可能です。
config で `RespectAdapter` や `OpisAdapter` を渡せばバックエンドを変更でき、公開 API と `{value, is_valid, errors}` の結果形式はそのまま維持されます。

アダプターのマッピング対象外となるルール（ファイルフィールド、クロスフィールド制約、カスタムロジック）は、拡張ポイント（`.extend()`、`.refine()`、`SV::custom()`）で補完できます。
境界は明示的で、マッピング不可のフィールドは `x-unmapped-fields` に列挙されるため、どのルールがサーバーサイドのみで検証されるかをクライアントが正確に把握できます。

## クイックスタート

```php
use SchemableValidator\SV;

$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),
  'email' => SV::string()->email(),
]);

// サーバーサイド検証（外部依存なし）
$result = $schema->toValidator()->validate($_POST)->getResult();

// クライアントサイド向けにエクスポート
echo $schema->toJson();
```

API の詳細は [SchemaBuilder](./schema-builder.md) と [Feature Guide](./feature-guide.md) を参照してください。
