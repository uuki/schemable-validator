# Interfaces

## WordPress Plugin

管理画面にメールテンプレート設定ページを追加し、`get_option()` でテンプレートを管理する。

### セットアップ

```php
use SchemableValidator\Interfaces\WordPress\Plugin;

new Plugin([
  'user' => [
    'title'       => 'Reply format (User)',
    'description' => 'Use {name}, {email}, {body} as placeholders.',
  ],
  'admin' => [
    'title'       => 'Reply format (Admin)',
    'description' => 'Use {name}, {email}, {body} as placeholders.',
  ],
]);
```

引数のキーに対して `SCHV_REPLY_FORMAT_FOR_{key}` という名前の WP オプションが登録される。  
管理画面は **WP Admin › Settings › Schemable Validator** に表示される。

### オプションキーの取得

```php
$plugin = new Plugin([...]);
$keys = $plugin->keysAll();
// ['user' => 'SCHV_REPLY_FORMAT_FOR_user', 'admin' => 'SCHV_REPLY_FORMAT_FOR_admin']
```

### テンプレートとの連携

`schv_template()` の `templates` にオプション名（文字列）を渡す。  
WordPress 環境では自動的に `get_option()` で値を取得する。

```php
$template = schv_template([
  'aliases'   => ['name' => 'name', 'email' => 'email', 'body' => 'body'],
  'templates' => [
    'user'  => 'SCHV_REPLY_FORMAT_FOR_user',
    'admin' => 'SCHV_REPLY_FORMAT_FOR_admin',
  ],
]);
```

---

## WordPress ヘルパー関数

プラグインが有効化されると以下のグローバル関数が使えるようになる。

| 関数 | 戻り値 | 説明 |
|:--|:--|:--|
| `schv_validator(array $schema, array $options = [])` | `Validator` | Validator インスタンスを生成 |
| `schv_template(array $options = [])` | `Template` | Template インスタンスを生成 |
| `schv_form()` | `FormController` | FormController インスタンスを生成 |

---

## WordPress 環境における注意点

### `$_REQUEST` とルーティングの衝突

WordPress は `$_REQUEST`（GET + POST のマージ）を URL ルーティングに使用する。  
フォームフィールド名に WordPress の予約済みクエリ変数（`name`、`p`、`page` など）を使うと、  
POST 送信時に WordPress が対応する投稿を探して 404 を返すことがある。

**対処:** `request` フィルターで POST 時に該当クエリ変数を除去する。

```php
add_filter('request', function ($qv) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['schv_action'] ?? '') === 'myform') {
    unset($qv['name']);
  }
  return $qv;
});
```

### `type="email"` とブラウザバリデーション

`<input type="email">` はブラウザが独自のバリデーションを行い、  
無効な値ではフォームが送信されないことがある。  
サーバー側でバリデーションを行う場合は `<form novalidate>` を付与する。

```html
<form method="post" novalidate>
```

---

## AbstractInterface

カスタム環境向けのインターフェース基底クラス。

```php
use SchemableValidator\Interfaces\AbstractInterface;

class MyInterface extends AbstractInterface {
  function __construct(array $templates) {
    // テンプレートを独自に取得・変換してから親に渡す
    parent::__construct($templates);
  }
}
```

| メソッド | 説明 |
|:--|:--|
| `getTemplate(string $name): string` | 指定テンプレートの文字列を返す |
| `getAll(): array` | 全テンプレートを配列で返す |
