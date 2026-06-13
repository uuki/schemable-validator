# SV::string() — 文字列型

テキスト入力に使う基本型。長さ・フォーマット・正規表現の制約をチェーンで付与できる。

```php
SV::string()
```

**JSON Schema 出力:**
```json
{ "type": "string" }
```

---

## .min(n) {#min}

**最小文字数**を設定する。

```php
SV::string()->min(int $n)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$n` | `int` | 許容する最小文字数（以上） |

**JSON Schema キーワード:** `minLength`

**用途:** 必須入力の空文字を防ぐ、本文に最低文字数を設ける。

```php
// 1文字以上（実質 required 相当）
SV::string()->min(1)

// 10文字以上の本文
SV::string()->min(10)
```

```json
{ "type": "string", "minLength": 10 }
```

---

## .max(n) {#max}

**最大文字数**を設定する。

```php
SV::string()->max(int $n)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$n` | `int` | 許容する最大文字数（以下） |

**JSON Schema キーワード:** `maxLength`

**用途:** DB カラム長に合わせる、表示領域に収める。

```php
// 100文字以内の名前
SV::string()->min(1)->max(100)

// 255文字以内のタイトル
SV::string()->max(255)
```

```json
{ "type": "string", "minLength": 1, "maxLength": 100 }
```

---

## .email() {#email}

メールアドレス形式を検証する。

```php
SV::string()->email()
```

**JSON Schema キーワード:** `format: "email"`

**用途:** 問い合わせフォームのメールアドレス欄。

```php
SV::string()->email()
```

```json
{ "type": "string", "format": "email" }
```

> SDK の `checkFormat` は `^[^\s@]+@[^\s@]+\.[^\s@]+$` で事前検証する。より厳密な検証はサーバー側（Respect `v::email()`）で行われる。

---

## .url() {#url}

URL 形式（`https://` または `http://` から始まる）を検証する。

```php
SV::string()->url()
```

**JSON Schema キーワード:** `format: "uri"`

**用途:** ウェブサイト URL、SNS プロフィール URL の入力欄。

```php
SV::string()->url()->optional()
```

```json
{ "type": "string", "format": "uri" }
```

---

## .pattern(p) {#pattern}

**正規表現**によるフォーマット検証。

```php
SV::string()->pattern(string $p)
```

| パラメータ | 型 | 説明 |
|:--|:--|:--|
| `$p` | `string` | 正規表現パターン（区切り文字なし） |

**JSON Schema キーワード:** `pattern`

**用途:** 電話番号・郵便番号・ユーザー名など、既定のフォーマットが存在する入力。

```php
// 日本の電話番号
SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional()

// 半角英数字とアンダースコア（ユーザー名）
SV::string()->min(3)->max(20)->pattern('^[a-zA-Z0-9_]+$')

// 日本の郵便番号
SV::string()->pattern('^\d{3}-\d{4}$')
```

```json
{ "type": "string", "pattern": "^[a-zA-Z0-9_]+$", "minLength": 3, "maxLength": 20 }
```

> パターンは区切り文字（`/`）なしで渡す。JSON Schema では `u` フラグが適用される。

---

## 組み合わせ例

```php
$schema = SV::object([
  // 名前: 1〜100文字
  'name'    => SV::string()->min(1)->max(100),

  // メールアドレス
  'email'   => SV::string()->email(),

  // 電話番号: 任意入力
  'tel'     => SV::string()->pattern('^(0\d{9,10}|0\d{1,4}-\d{1,4}-\d{3,4})$')->optional(),

  // ウェブサイト: null 許容
  'website' => SV::string()->url()->nullable()->optional(),

  // 本文: 10〜1000文字
  'body'    => SV::string()->min(10)->max(1000),
]);
```
