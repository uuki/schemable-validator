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
| [`SV::array(items)`](./array) | `"array"` | 配列。各要素のスキーマを指定できる |
| [`SV::file(accept)`](./extended) | —（JSON Schema 非対応） | ファイルアップロード |
| [`SV::respect(rule)`](./extended) | —（JSON Schema 非対応） | Respect/Validation ルールの直接指定 |
| [`SV::postalCode(country)`](./extended#postalcode) | —（JSON Schema 非対応） | 国別郵便番号 |
| [`SV::creditCard()`](./extended#creditcard) | —（JSON Schema 非対応） | クレジットカード番号（Luhn） |
| [`SV::iban()`](./extended#iban) | —（JSON Schema 非対応） | IBAN |

## 文字列制約

| 表現 | JSON Schema キーワード | 説明 |
|:--|:--|:--|
| [`.min(n)`](./string#min) | `minLength` | 最小文字数 |
| [`.max(n)`](./string#max) | `maxLength` | 最大文字数 |
| [`.email()`](./string#email) | `format: "email"` | メールアドレス形式 |
| [`.url()`](./string#url) | `format: "uri"` | URL 形式 |
| [`.pattern(p)`](./string#pattern) | `pattern` | 正規表現 |
| [`.date()`](./string#date) | `format: "date"` | 日付（YYYY-MM-DD） |
| [`.dateTime()`](./string#datetime) | `format: "date-time"` | 日時（RFC 3339） |
| [`.time()`](./string#time) | `format: "time"` | 時刻（HH:mm:ss） |
| [`.uuid()`](./string#uuid) | `format: "uuid"` | UUID |
| [`.ipv4()`](./string#ipv4) | `format: "ipv4"` | IPv4 アドレス |
| [`.ipv6()`](./string#ipv6) | `format: "ipv6"` | IPv6 アドレス |
| [`.slug()`](./string#slug) | `pattern` | URL スラッグ（小文字英数字・ハイフン） |
| [`.domain()`](./string#domain) | `format: "hostname"` | ドメイン名 |

## 配列制約

| 表現 | JSON Schema キーワード | 説明 |
|:--|:--|:--|
| [`.minItems(n)`](./array#minitems) | `minItems` | 最小要素数 |
| [`.maxItems(n)`](./array#maxitems) | `maxItems` | 最大要素数 |

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
| [`.when(field, expr, require)`](./object#when) | 条件付き必須 |
| [`.toJsonSchema()`](./object#tojsonschema) | JSON Schema (array) として出力 |
| [`.toJson()`](./object#tojson) | JSON Schema (string) として出力 |
| [`.toValidator()`](./object#tovalidator) | Respect/Validation ベースの `Validator` を生成 |

## 条件式（when() の第2引数）

`.when()` に渡す比較式。スカラー値を直接渡した場合は `SV::equal()` と等価。

| 式 | 比較演算子 | 用途 |
|:--|:--|:--|
| [`SV::equal($value)`](./object#when-expressions) | `===` | 値が一致するとき |
| [`SV::notEqual($value)`](./object#when-expressions) | `!==` | 値が一致しないとき |
| [`SV::greaterThanOrEqual($n)`](./object#when-expressions) | `>=` | 値が $n 以上のとき |
| [`SV::lessThanOrEqual($n)`](./object#when-expressions) | `<=` | 値が $n 以下のとき |
| [`SV::greaterThan($n)`](./object#when-expressions) | `>` | 値が $n より大きいとき |
| [`SV::lessThan($n)`](./object#when-expressions) | `<` | 値が $n 未満のとき |
| [`SV::field('name')`](./object#when-expressions) | — | 比較先を別フィールドの値にする |
