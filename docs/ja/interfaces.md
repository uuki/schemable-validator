# インターフェース

## WordPress Plugin

管理画面にメールテンプレート設定ページを追加し、`get_option()` でテンプレートを管理します。

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

引数のキーに対して `SCHV_REPLY_FORMAT_FOR_{key}` という名前の WP オプションが登録されます。  
管理画面は **WP Admin › Settings › Schemable Validator** に表示されます。

### オプションキーの取得

```php
$plugin = new Plugin([...]);
$keys = $plugin->keysAll();
// ['user' => 'SCHV_REPLY_FORMAT_FOR_user', 'admin' => 'SCHV_REPLY_FORMAT_FOR_admin']
```

### テンプレートとの連携

`schv_template()` の `templates` にオプション名（文字列）を渡します。  
WordPress 環境では自動的に `get_option()` で値を取得します。

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

### スキーマエディタ

スキーマエディタは、PHP を書かずにバリデーションスキーマを定義できる管理画面です。
スキーマは以下の 2 箇所に保存されます。

- **テーマディレクトリ**（`{有効テーマ}/schv-schemas/{slug}.json`）— git で管理できます
- **wp_options**（`schv_schema_{slug}`）— 後方互換のフォールバックです

`StoredSchemaProvider` はテーマファイルを優先して読み込み、存在しなければ `wp_options` にフォールバックします。

#### エクスポートとインポート

保存済みスキーマごとに **Export** ボタンがあり、JSON ファイルをダウンロードできます。
**Import** フォームでは `.json` ファイルとスラッグを指定してアップロードすると、テーマディレクトリと `wp_options` の両方に書き込まれます。

#### マージ衝突の検出

`schv_register_code_fields()` でコード側のフィールド名を登録しておくと、スキーマエディタが GUI 定義との重複をワーニングとして表示します。
マージ時はコード側の定義が優先されます。

```php
// "contact" スキーマに対して、コード側で定義しているフィールドを登録します。
// GUI でこれらのフィールドが定義されている場合、スキーマエディタが警告を表示します。
schv_register_code_fields('contact', ['company_name', 'attachment']);
```

#### スキーマエンドポイントのキャッシュ制御

`schv_register_schema()` は JSON Schema を REST で配信する際に `Cache-Control: public, max-age=60, stale-while-revalidate=3600` と ETag を付与します。
本番環境でデプロイ直後に即座に反映したい場合は、`schv_schema_cache_headers` フィルターでヘッダーを上書きできます。

```php
add_filter('schv_schema_cache_headers', function ($headers) {
    return ['Cache-Control' => 'no-cache, must-revalidate', 'ETag' => $headers['ETag']];
});
```

---

## WordPress ヘルパー関数

プラグインが有効化されると以下のグローバル関数が使えるようになります。

| 関数 | 戻り値 | 説明 |
|:--|:--|:--|
| `schv_validator(array $schema, array $options = [], ?MessageDict $dict = null)` | `Validator` | Validator インスタンスを生成 |
| `schv_message_dict()` | `MessageDict` | `schv_message_dict` フィルター経由でサイト全体の辞書を返す |
| `schv_template(array $options = [])` | `Template` | Template インスタンスを生成 |
| `schv_form()` | `FormController` | FormController インスタンスを生成 |
| `schv_stored_schema(string $slug)` | `StoredSchemaProvider` | テーマディレクトリまたは `wp_options` からスキーマを読み込む |
| `schv_register_schema(string $route, SchemaProviderInterface $provider)` | `void` | JSON Schema を配信する REST エンドポイントを登録 |
| `schv_register_code_fields(string $slug, string[] $fields)` | `void` | マージ衝突検出のためコード側フィールド名を登録 |

---

## WordPress 環境における注意点

### `$_REQUEST` とルーティングの衝突

WordPress は `$_REQUEST`（GET + POST のマージ）を URL ルーティングに使用します。  
フォームフィールド名に WordPress の予約済みクエリ変数（`name`、`p`、`page` など）を使うと、  
POST 送信時に WordPress が対応する投稿を探して 404 を返すことがあります。

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
無効な値ではフォームが送信されないことがあります。  
サーバー側でバリデーションを行う場合は `<form novalidate>` を付与してください。

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
