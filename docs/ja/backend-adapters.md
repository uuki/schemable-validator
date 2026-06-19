# バックエンドアダプタとガバナンス

PHP コアは差し替え可能な**バックエンドアダプタ**境界を通して検証します。
Respect/Validation の知識はすべてこの境界の背後に隔離されているため、公開 API や
`{value, is_valid, errors}` の結果形を変えずに検証エンジンを差し替え — あるいは
完全に外す — ことができます。

---

## 契約

```php
// packages/core/Validation/BackendAdapter.php
interface BackendAdapter {
    public function compile(array $jsonSchema): ExecutableValidator;
}

// packages/core/Validation/ExecutableValidator.php
interface ExecutableValidator {
    // array<string, array{value: mixed, is_valid: bool, errors: ?string}> を返す
    public function validate(array $data): array;
}
```

`compile()` は JSON Schema 2020-12 オブジェクト（`properties` / `required` と所有
`x-*` 拡張）を `ExecutableValidator` に変換します。executable は**フィールド単位**で
検証し共通結果形を返します。`x-transform` と `x-when` は executable ではなく呼び出し側
（`Validator` / コンフォーマンスランナー）が適用します。

メッセージはエンジン中立です。各アダプタは自エンジンの失敗を中立ルール名へ写像し、
共有カタログ（[MessageDict](./message-dict.md) 参照）でテキストを解決するため、どの
バックエンドでも同一文字列を出力します。

---

## 組み込みアダプタ

| アダプタ | 依存 | Coercion Contract v1 | 用途 |
|:--|:--|:--|:--|
| `NativeAdapter`（既定） | なし | あり | 依存ゼロ・FE 等価。既定エンジン |
| `RespectAdapter` | `respect/validation`（任意） | あり | Respect 全ルール / エスケープハッチ |
| `OpisAdapter` | `opis/json-schema`（任意） | **なし**（厳密 JSON Schema） | 型付き JSON 入力・構造検証 |

- **NativeAdapter** が既定エンジンで、`SchemaBuilder::toValidator()` と
  `Validator::fromJsonSchema()` に配線済み。FE `constraint.ts`/`validator.ts` の意味論を
  依存ゼロで PHP 移植し、Coercion Contract v1 を honor（`integer` に `"42"` を FE 同様受理）。
  全 `conformance/*.json` フィクスチャで検証済み（`tests/Conformance/NativeConformanceTest.php`）。
- **RespectAdapter** はオプトイン（`toValidator()`/`fromJsonSchema()` に渡す）。Respect エスケープ
  ハッチ（`SV::respect` / `RespectRules`）や生 `v` スキーマでも暗黙に使われる。任意依存
  `respect/validation` が必要。
- **OpisAdapter** は厳密 JSON Schema 意味論（coercion なし）。フォーム文字列 `"42"` は
  `type: integer` で不合格。型付き JSON 向け。

### Coercion の差異（重要）

`NativeAdapter`/`RespectAdapter` は Coercion Contract v1 に従いフォーム文字列を受理しますが、
`OpisAdapter` は受理しません。入力が `$_POST` 文字列ではなく既に型付き JSON の場合のみ
`OpisAdapter` を選んでください。

---

## 任意依存

既定経路（NativeAdapter ＋ 依存ゼロの NativeFileValidator ＋ SV::custom）は外部バリデータ不要。
両エンジンパッケージは composer `suggest` で、オプトイン時のみロードされます。

- `respect/validation` — `RespectAdapter`、Respect エスケープハッチ（`RespectRules` /
  `SV::respect` / `postalCode` / `creditCard` / `iban`）、生 `v` スキーマを有効化。Respect の
  factory はこれらの経路でのみ遅延初期化され、Native 既定では一切ロードされません。

  ```
  composer require respect/validation
  ```

- `opis/json-schema` — `OpisAdapter` を有効化。未インストールで生成すると明確な実行時エラー。

  ```
  composer require opis/json-schema
  ```

---

## カスタムアダプタの書き方

```php
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;

final class MyAdapter implements BackendAdapter {
    public function compile(array $jsonSchema): ExecutableValidator {
        // validate(array $data) が
        // [field => ['value' => ..., 'is_valid' => bool, 'errors' => ?string]]
        // を返すものを返す
        return new MyExecutableValidator(/* ... */);
    }
}

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new MyAdapter());
```

バックエンド間でメッセージを揃えるには、エンジン固有文字列を出すのではなく、失敗を
中立ルール語彙へ写像し `DefaultMessages` / `MessageDict::interpolate()` で解決してください。

---

## フロントエンドアダプタ

クライアントパッケージはネイティブ検証に加え、Zod / Valibot アダプタを subpath export
（`@uuki/schemable-validator-client/zod`・`/valibot`）として提供します。第三者アダプタ
（Svelte、React Hook Form …）は同じ JSON Schema + `x-*` 契約を消費します。
[クライアントアダプタのドキュメント](./client-adapter.md)を参照してください。

---

## ガバナンス: `x-*` 拡張 vs `$vocabulary`

所有拡張（`x-when`・`x-custom-fields`・`x-transform`・インライン `errorMessage`）は、
正式な JSON Schema `$vocabulary` へ昇格させず、意図的に `x-*` のままにしています。理由:

- `x-*` キーは汎用 JSON Schema バリデータがエラーなく無視するため、スキーマの可搬性が保たれる。
- `$vocabulary` への昇格は、実際の外部消費者が汎用バリデータで我々のスキーマを検証し
  `x-when` / `x-custom-fields` を黙殺して**過少検証**が起きたとき — すなわち採用駆動の
  トリガ — に限る。
- 昇格時は `x-*` 表記を 1 メジャーサイクルの間エイリアスとして併記する。

それまでは PHP バックエンドと FE 評価器の双方がこれら拡張の意味論を直接掌握しており、
それがエンジン中立メッセージ保証とクロススタック・コンフォーマンススイートを可能にしています。
