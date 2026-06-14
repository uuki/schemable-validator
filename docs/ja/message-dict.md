# MessageDict - エラーメッセージの多言語化

`MessageDict` は、バリデーションエラーメッセージをフィールド×ルール単位で辞書的に定義できる値オブジェクトです。

---

## 基本的な使い方

```php
use SchemableValidator\I18n\MessageDict;

// 日本語プリセット（デフォルトメッセージを一括適用）
$dict = MessageDict::ja();

// 英語（Respect デフォルトへのパススルー）
$dict = MessageDict::en();
```

### カスタム定義を追加する

```php
$dict = MessageDict::ja([
  // フィールド全体を上書き（ルール問わず同じメッセージ）
  'email' => 'メールアドレスが無効です',

  // ルール単位で上書き
  'name' => [
    'length' => '名前は2〜50文字で入力してください',
  ],
]);
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

`resolve(field, ruleId, fallback)` は次の順で解決します。

| 優先度 | 条件 | 使用するメッセージ |
|:--|:--|:--|
| 1 | `$definitions[$field][$ruleId]` が存在する | フィールド×ルール固有 |
| 2 | `$definitions[$field]` が文字列 | フィールド全体の省略形 |
| 3 | `$defaults[$ruleId]` が存在する | ロケールプリセット |
| 4 | 上記いずれも該当しない | Respect デフォルト（英語） |

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

`MessageDict::ja()` が適用するデフォルトメッセージです。

| ルール ID | デフォルトメッセージ |
|:--|:--|
| `stringType` | 文字列で入力してください |
| `length` | 文字数が範囲外です |
| `email` | 有効なメールアドレスを入力してください |
| `notEmpty` | 入力必須です |
| `notOptional` | 入力必須です |
| `integer` / `intType` | 整数で入力してください |
| `numeric` | 数値で入力してください |
| `url` | 有効なURLを入力してください |
| `regex` | 入力形式が正しくありません |
| `in` / `anyOf` | 選択肢から選んでください |
| `required` | 必須項目です（条件付き必須時） |
