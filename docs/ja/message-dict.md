# MessageDict - エラーメッセージの多言語化

`MessageDict` は、バリデーションエラーメッセージをフィールド×**中立ルール名**単位で辞書的に定義できる値オブジェクトです。

> **破壊的変更（ルール ID リキーイング）**。メッセージのキーは Respect 内部 ID
> （`stringType`/`length`/`intType`/`numeric`/`in` …）から、エンジン中立な語彙
> （`minLength`/`maxLength`/`minimum`/`maximum`/`email`/`uri`/`pattern`/`enum`/
> `string`・`integer`・`number`・`boolean` …）へ変更されました。完全な対応表は
> 下の[移行ガイド](#respect-ルール-id-からの移行)を参照。これにより Respect/Opis/
> ネイティブのどのバックエンドでも同一メッセージになります。

---

## 基本的な使い方

```php
use SchemableValidator\I18n\MessageDict;

// 日本語プリセット（デフォルトメッセージを一括適用）
$dict = MessageDict::ja();

// 英語: デフォルトは正準カタログ（DefaultMessages）が供給するため、
// en() 自体に定義は不要。
$dict = MessageDict::en();
```

### カスタム定義を追加する

```php
$dict = MessageDict::ja([
  // フィールド全体を上書き（ルール問わず同じメッセージ）
  'email' => 'メールアドレスが無効です',

  // ルール単位で上書き（中立ルール名キー）
  'name' => [
    'minLength' => '名前は2文字以上で入力してください',
    'maxLength' => '名前は50文字以内で入力してください',
  ],
]);
```

### プレースホルダー展開

テンプレートには `{var}`（ICU 風の `{var, type}` も可・type は無視）を含められ、
失敗したルールの値で展開されます — 長さ/範囲は `{min}`/`{max}`、`enum` は `{values}`。
同じ置換が FE 側でも実行されます。

```php
$dict = MessageDict::ja([
  'name' => ['minLength' => '{min}文字以上で入力してください'],
]);
// → "2文字以上で入力してください"
```

---

## Validator への渡し方

### 直接コンストラクタ

```php
use SchemableValidator\Validator;
use SchemableValidator\I18n\MessageDict;

$validator = new Validator($schema, [], [], MessageDict::ja());
```

### SchemaBuilder 経由

```php
use SchemableValidator\SV;
use SchemableValidator\I18n\MessageDict;

$schema = SV::object([
  'name'  => SV::string()->min(2)->max(50),
  'email' => SV::string()->email(),
])->withMessages(MessageDict::ja([
  'email' => 'メールアドレスが無効です',
]));

$result = $schema->toValidator()->validate($_POST)->getResult();
```

---

## WordPress ヘルパー

### 個別指定

`schv_validator()` の第3引数に渡します。

```php
use SchemableValidator\I18n\MessageDict;

$validator = schv_validator($schema, [], MessageDict::ja([
  'email' => 'メールアドレスが無効です',
]));
```

### サイト全体のデフォルト（フィルター）

`schv_message_dict` フィルターで辞書を上書きすると、`schv_validator()` 呼び出し時に自動で適用されます。

```php
add_filter('schv_message_dict', function (MessageDict $dict): MessageDict {
  return $dict->merge([
    'email' => 'メールアドレスを正しく入力してください',
    'name'  => ['length' => '名前は2〜50文字です'],
  ]);
});

// 以降の schv_validator() 呼び出しにフィルターが適用される
$validator = schv_validator($schema);
```

> 個別指定（第3引数）はフィルターより優先されます。

---

## メッセージ解決の優先順位

フィールドがルールに失敗すると、バックエンドは次の順（上が最優先）で解決します。
`MessageDict`（1〜3）が常に最初に `resolve(field, neutralRuleId, fallback, vars)`
で参照され、スキーマのインライン `errorMessage` と正準カタログは fallback として渡されます。

| 優先度 | ソース | キー |
|:--|:--|:--|
| 1 | `MessageDict` フィールド×ルール（`$definitions[$field][$rule]`） | 中立ルール名 |
| 2 | `MessageDict` フィールド全体の文字列（`$definitions[$field]`） | — |
| 3 | `MessageDict` ロケールプリセット（`$defaults[$rule]`・例 `ja()`） | 中立ルール名 |
| 4 | スキーマのインライン `errorMessage[keyword]` | JSON Schema keyword |
| 5 | 正準カタログ（`DefaultMessages`） | 中立ルール名 |
| 6 | エンジン文言（Respect/Opis）— 中立写像の無いルールのみ | — |

> したがって設定済みの `MessageDict`（ロケールプリセット含む）は、スキーマの
> インライン `errorMessage` より優先されます（運用者の辞書が最終決定権を持つ）。

---

## merge() - イミュータブルな合成

`merge()` は元のインスタンスを変更せず、新しい `MessageDict` を返します。

```php
$base  = MessageDict::ja();
$extra = $base->merge(['tel' => '電話番号の形式が正しくありません']);

// $base は変更されない
```

### フィールド内のルール定義は保持される

両辺のフィールド値が配列の場合、1段階深くマージされます。既存のルール定義は上書きされません。

```php
$base = new MessageDict([
  'name' => [
    'length' => '文字数が範囲外です',
    'email'  => '無効なメールです',
  ],
]);

$next = $base->merge([
  'name' => ['length' => '名前は2〜50文字で入力してください'],
]);

// 'length' は新しい値に、'email' は元の値を保持
// $next->resolve('name', 'length', '') → '名前は2〜50文字で入力してください'
// $next->resolve('name', 'email',  '') → '無効なメールです'
```

フィールドの型が変わる場合（文字列→配列、配列→文字列）は、`merge()` 側が優先されます。

---

## 日本語プリセット一覧

`MessageDict::ja()` が適用するデフォルトメッセージ（中立語彙キー）です。

| 中立ルール名 | デフォルトメッセージ |
|:--|:--|
| `string` | 文字列で入力してください |
| `integer` | 整数で入力してください |
| `number` | 数値で入力してください |
| `boolean` | 真偽値で入力してください |
| `minLength` | 最低{min}文字で入力してください |
| `maxLength` | 最大{max}文字まで入力できます |
| `minimum` | {min}以上で入力してください |
| `maximum` | {max}以下で入力してください |
| `email` | 有効なメールアドレスを入力してください |
| `uri` | 有効なURLを入力してください |
| `date` / `date-time` / `time` | 有効な日付/日時/時刻を入力してください |
| `uuid` | 有効なUUIDを入力してください |
| `ipv4` / `ipv6` | 有効なIPv4/IPv6アドレスを入力してください |
| `hostname` | 有効なホスト名を入力してください |
| `pattern` | 入力形式が正しくありません |
| `enum` | 選択肢から選んでください |
| `required` | 必須項目です（条件付き必須時） |

---

## Respect ルール ID からの移行

キーは Respect 内部ルール ID から中立語彙へ変更されました。カスタム `MessageDict`
定義および `schv_message_dict` フィルターを更新してください。

| 旧キー（Respect ID） | 新キー（中立） |
|:--|:--|
| `stringType` | `string` |
| `intType` / `integer` | `integer` |
| `numeric` | `number` |
| （なし） | `boolean` |
| `length` | `minLength` / `maxLength`（失敗した境界で分割） |
| （なし） | `minimum` / `maximum` |
| `email` | `email`（変更なし） |
| `url` | `uri` |
| `regex` | `pattern` |
| `in` / `anyOf` | `enum` |
| `notEmpty` / `notOptional` | `required` |

変更なしは `email` のみです。最大の変更は `length` で、2 つの境界に個別メッセージと
`{min}`/`{max}` プレースホルダーを持たせるため `minLength`/`maxLength` に分割されました。
