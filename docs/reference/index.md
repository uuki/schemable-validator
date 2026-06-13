# Schema Reference

`SV::object()` に渡すフィールド定義の全表現を一覧する。

各表現は JSON Schema draft 2020-12 のキーワードに対応し、サーバー側バリデーション（Respect/Validation）とクライアント側バリデーション（SDK / Zod）の両方に適用される。

---

## フィールド型

| 表現 | JSON Schema `type` | 説明 |
|:--|:--|:--|
| [`SV::string()`](./string) | `"string"` | テキスト入力。長さ・形式・正規表現の制約を持てる |
| [`SV::integer()`](./number) | `"integer"` | 整数。範囲制約を持てる |
| [`SV::number()`](./number) | `"number"` | 整数または小数。範囲制約を持てる |
| [`SV::boolean()`](./scalar) | `"boolean"` | 真偽値 |
| [`SV::enum(values)`](./scalar) | `"string"` + `enum` | 選択肢から1つを選ぶ |
| [`SV::file(accept)`](./extended) | —（JSON Schema 非対応） | ファイルアップロード |
| [`SV::respect(rule)`](./extended) | —（JSON Schema 非対応） | Respect/Validation ルールの直接指定 |

## 文字列制約

| 表現 | JSON Schema キーワード | 説明 |
|:--|:--|:--|
| [`.min(n)`](./string#min) | `minLength` | 最小文字数 |
| [`.max(n)`](./string#max) | `maxLength` | 最大文字数 |
| [`.email()`](./string#email) | `format: "email"` | メールアドレス形式 |
| [`.url()`](./string#url) | `format: "uri"` | URL 形式 |
| [`.pattern(p)`](./string#pattern) | `pattern` | 正規表現 |

## 数値制約

| 表現 | JSON Schema キーワード | 説明 |
|:--|:--|:--|
| [`.min(n)`](./number#min) | `minimum` | 最小値 |
| [`.max(n)`](./number#max) | `maximum` | 最大値 |

## 修飾子

| 表現 | 効果 |
|:--|:--|
| [`.optional()`](./modifiers#optional) | `required` 配列から除外。未入力を許容する |
| [`.nullable()`](./modifiers#nullable) | `type` を `[type, "null"]` に拡張。`null` 値を許容する |

## オブジェクト・出力

| 表現 | 説明 |
|:--|:--|
| [`SV::object(fields)`](./object#object) | フィールドの集合を定義する |
| [`.toJsonSchema()`](./object#tojsonschema) | JSON Schema (array) として出力 |
| [`.toJson()`](./object#tojson) | JSON Schema (string) として出力 |
| [`.toValidator()`](./object#tovalidator) | Respect/Validation ベースの `Validator` を生成 |
