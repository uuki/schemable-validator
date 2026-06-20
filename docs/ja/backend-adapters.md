# バックエンドアダプタとガバナンス

PHP コアはフィールド検証を差し替え可能な**バックエンドアダプタ**経由で実行します。
アダプタを切り替えても公開 API や `{value, is_valid, errors}` の結果形式は変わりません。

---

## インターフェース

```php
// packages/core/Validation/BackendAdapter.php
interface BackendAdapter {
    public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator;
}

// packages/core/Validation/ExecutableValidator.php
interface ExecutableValidator {
    // array<string, array{value: mixed, is_valid: bool, errors: ?string}> を返す
    public function validate(array $data): array;
}
```

`compile()` は JSON Schema 2020-12 オブジェクト（`properties`、`required`、`x-*` 拡張）を受け取り `ExecutableValidator` を返します。
executable はフィールドごとに検証し、共通の結果形式を返します。
`x-transform` と `x-when` は、executable ではなく呼び出し側（`Validator` / コンフォーマンスランナー）が適用します。

各アダプタはエンジンの検証失敗を中立のルール ID に変換し、共有カタログ（[MessageDict](./message-dict.md) 参照）でエラーテキストを解決します。
どのバックエンドを使っても、同じ違反に対して同一のエラー文字列が得られます。

---

## アダプタの指定方法

### アダプタを指定しない場合

アダプタを渡さない場合は `NativeAdapter` が自動的に使われます。
外部依存がなく FE の検証動作に準拠しているため、多くのプロジェクトで追加設定は不要です。

```php
$result = SV::object([
    'name'  => SV::string()->min(2),
    'email' => SV::string()->email(),
])->toValidator()->validate($_POST);
```

### 任意のアダプタを指定する場合

`toValidator()` の第 2 引数に `'adapter'` キーでアダプタのインスタンスを渡します。
渡したアダプタがすべてのマッパブルフィールドの検証エンジンになります。

#### RespectAdapter を使う場合

まずパッケージをインストールします。

```
composer require respect/validation
```

`RespectAdapter` を `toValidator()` に渡します。

```php
use SchemableValidator\Adapters\Respect\RespectAdapter;

$validator = SV::object([
    'name' => SV::string()->min(2),
    'age'  => SV::integer(),
])->toValidator([], ['adapter' => new RespectAdapter()]);

$result = $validator->validate(['name' => 'Alice', 'age' => '30'])->getResult();
// $result['age']['is_valid'] === true（フォーム文字列は引き続き受け入れられます）
```

内部では、SV の fluent API は `toValidator()` の中で一度 JSON Schema（中立 IR）に変換され、`RespectAdapter` がその JSON Schema キーワードを Respect ルールに写像して実行します。

```
SV::string()->min(2)->email()
      ↓ toJsonSchema()
{ "type": "string", "minLength": 2, "format": "email" }
      ↓ RespectAdapter::compile()
v::stringType() + v::length(2, null) + v::email()
      ↓
Respect/Validation で検証
```

フォーム文字列は Coercion Contract v1 に従い引き続き受け入れられるため、`"30"` は `integer` に合格します。

#### OpisAdapter を使う場合

まずパッケージをインストールします。

```
composer require opis/json-schema
```

`OpisAdapter` を `toValidator()` に渡します。

```php
use SchemableValidator\Adapters\Opis\OpisAdapter;

$validator = SV::object(['count' => SV::integer()])
    ->toValidator([], ['adapter' => new OpisAdapter()]);

$result = $validator->validate(json_decode($body, true))->getResult();
// "5" のような文字列は type: integer で不合格（coercion なし）
```

内部では、JSON Schema を中間変換なしで opis/json-schema にそのまま渡します。
RespectAdapter のように JSON Schema キーワードを別のルールへ写像する工程はありません。

```
SV::object(['count' => SV::integer()])
      ↓ toJsonSchema()
{ "type": "object", "properties": { "count": { "type": "integer" } } }
      ↓ OpisAdapter::compile()
opis/json-schema が JSON Schema をそのまま検証
      ↓
厳密な JSON Schema 検証（coercion なし）
```

`"5"` のようなフォーム文字列は `type: integer` で不合格となります。
`$_POST` 文字列ではなく型付き JSON を受け取る場合に使用してください。

#### `Validator::fromJsonSchema()` で指定する

生の JSON Schema から直接構築する場合は、**第 5 引数**にアダプタを渡します。

```php
use SchemableValidator\Orchestration\Validator;
use SchemableValidator\Adapters\Opis\OpisAdapter;

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new OpisAdapter());
```

---

## 検証ドライバの注入

ファイルアップロードの検証など、JSON Schema で表現できないバリデーションロジックは、ドライバとして注入できるよう設計しています。
コアはシステムレベルの依存から切り離されたまま、動作をテスト環境や本番環境に応じて差し替えられます。

既定の**ファイル検証ドライバ**は `NativeFileValidator` です。
PHP の `finfo` 拡張を使って MIME タイプを許可リストと照合し、外部依存はありません。

### カスタムファイル検証ドライバを注入する

`FileValidationDriver` を実装したクラスを `toValidator()` の第 2 引数に `'fileDriver'` キーで渡します。

```php
use SchemableValidator\Validation\FileValidationDriver;

final class MyS3FileValidator implements FileValidationDriver {
    public function validate(array $file, array $config): array {
        // $file: {name, type, tmp_name, error, size}
        // $config: ['accept' => ['image/jpeg', ...]]
        // 戻り値: {value, is_valid, errors}
    }
}

$validator = SV::object(['avatar' => SV::file()])
    ->toValidator([], ['fileDriver' => new MyS3FileValidator()]);
```

両方を同時に差し替える場合は同じ配列にまとめます。

```php
$validator = SV::object([
    'name'   => SV::string()->min(2),
    'avatar' => SV::file(),
])->toValidator([], [
    'adapter'    => new RespectAdapter(),
    'fileDriver' => new MyS3FileValidator(),
]);
```

### 画像検証ドライバを注入する

`FileValidationDriver` は MIME タイプを確認しますが、ピクセル寸法やファイルサイズは検査しません。
`ImageDriver` がその役割を担います。MIME 型の受理後に実行され、寸法とサイズを検証します。

`SV::file()` の第 2 引数に画像制約を渡し、`'imageDriver'` キーでドライバを注入します。

```php
use SchemableValidator\SV;
use SchemableValidator\Adapters\Native\NativeImageDriver;

$schema = SV::object([
    'avatar' => SV::file(
        ['image/jpeg', 'image/png', 'image/webp'],
        [
            'maxWidth'  => 2048,
            'maxHeight' => 2048,
            'maxSize'   => 5 * 1024 * 1024, // 5 MB（バイト単位）
        ]
    ),
]);

$result = $schema
    ->toValidator([], ['imageDriver' => new NativeImageDriver()])
    ->validate($_POST)
    ->validateFiles($_FILES)
    ->getResult();
```

`NativeImageDriver` は `getimagesize()` で画像ヘッダのみを読み取ります。ピクセルデータのデコードは行わず、外部依存もありません。
ファイルサイズはヘッダ解析より先に確認するため、上限超過のファイルは画像を読まずに弾かれます。

利用できる制約キー:

| キー | 型 | 説明 |
|:--|:--|:--|
| `maxWidth` | `int` | 最大幅（ピクセル） |
| `maxHeight` | `int` | 最大高さ（ピクセル） |
| `minWidth` | `int` | 最小幅（ピクセル） |
| `minHeight` | `int` | 最小高さ（ピクセル） |
| `maxSize` | `int` | 最大ファイルサイズ（バイト） |

`imageDriver` が実行される条件は、ファイルが `fileDriver`（MIME 受理済み）を通過していること、かつそのフィールドに少なくとも 1 つの画像制約が宣言されていることです。
画像制約のないファイルフィールドはドライバを無視するため、画像フィールドと非画像ファイルフィールドが混在するスキーマでも `NativeImageDriver` インスタンスをそのまま共有できます。

### CAPTCHA 検証ドライバを注入する

`'captchaDriver'` キーで `CaptchaDriver` を渡し、バリデータで `validateCaptcha()` を呼び出します。
3 つのプロバイダが組み込み実装として提供されています。

```php
use SchemableValidator\Adapters\Captcha\ReCaptchaV3Driver;
use SchemableValidator\Adapters\Captcha\HCaptchaDriver;
use SchemableValidator\Adapters\Captcha\TurnstileDriver;
use SchemableValidator\Adapters\Captcha\NullCaptchaDriver;

// Google reCAPTCHA v3
$validator = $schema->toValidator([], [
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);

// hCaptcha
$validator = $schema->toValidator([], [
    'captchaDriver' => new HCaptchaDriver('YOUR_SECRET'),
]);

// Cloudflare Turnstile
$validator = $schema->toValidator([], [
    'captchaDriver' => new TurnstileDriver('YOUR_SECRET'),
]);

// テスト・ローカル開発用（常に通る。false を渡すと常に弾く）
$validator = $schema->toValidator([], [
    'captchaDriver' => new NullCaptchaDriver(),
]);
```

`validate()` の後に `validateCaptcha()` を呼び出します。

```php
$result = $validator
    ->validate($_POST)
    ->validateCaptcha(['action' => 'contact']) // action 検証はオプション（reCAPTCHA v3 のみ有効）
    ->getResult();
```

トークンは次の POST フィールドのうち最初に値があるものから読み取ります: `g-recaptcha-response`、`h-captcha-response`、`cf-turnstile-response`、`recaptcha_token`。

結果は `$result['captcha']` に書き込まれます。

```json
{ "value": 0.9, "is_valid": true, "errors": null }
```

`value` は reCAPTCHA v3 のスコア（0.0〜1.0）です。スコアを返さないプロバイダでは `null` になります。

`ReCaptchaV3Driver` にはスコア閾値とエンドポイントのオプションがあります。

```php
new ReCaptchaV3Driver(
    secret:   'YOUR_SECRET',
    minScore: 0.5,   // デフォルト 0.5。これを下回るスコアは弾く
    endpoint: 'https://www.recaptcha.net/recaptcha/api/siteverify', // 別 Google エンドポイント
)
```

エンドポイントは Google の公式 siteverify URL 2 種のみ指定可能です。それ以外を渡すとコンストラクタ時に例外が発生します。

**セキュリティ特性。**
3 つのドライバはいずれも `CurlController` 経由で検証リクエストを送信します。HTTPS のみ許可、リダイレクト無効、プライベート・予約済み IP アドレスをブロック（IPv4: RFC 1918・ループバック・リンクローカル、IPv6: ULA・ループバック・マルチキャスト・リンクローカル・IPv4 マッピング済み）し、タイムアウトは 30 秒です。
各ドライバはエンドポイントをハードコードしているため、呼び出し元が指定した URL がネットワークに届くことはありません。
内部エラー（エンドポイント URL、プロバイダからのエラーコードなど）は `error_log()` にのみ出力し、呼び出し元には `"CAPTCHA verification failed"` というメッセージのみ返します。

**使用例。**

```php
$validator = $schema->toValidator([
    'captchaDriver' => new ReCaptchaV3Driver('YOUR_SECRET'),
]);
$result = $validator->validate($_POST)->validateCaptcha()->getResult();
```

---

## 組み込みアダプタ

| アダプタ | 依存 | Coercion Contract v1 | 用途 |
|:--|:--|:--|:--|
| `NativeAdapter`（既定） | なし | あり | 外部依存なし、FE 動作に準拠 |
| `RespectAdapter` | `respect/validation`（任意） | あり | Respect/Validation を検証エンジンとして使用 |
| `OpisAdapter` | `opis/json-schema`（任意） | なし（厳密 JSON Schema） | 型付き JSON 入力、構造検証 |

**NativeAdapter** が既定で、`SchemaBuilder::toValidator()` と `Validator::fromJsonSchema()` から自動的に使われます。
外部依存なしで FE `constraint.ts`/`validator.ts` の動作を PHP 実装しており、`integer` フィールドに `"42"` のようなフォーム文字列を受け入れます（Coercion Contract v1）。
全 `conformance/*.json` フィクスチャで動作を検証済みです（`tests/Conformance/NativeConformanceTest.php`）。

**RespectAdapter** は明示的に渡す必要があります。
Respect エスケープハッチ（`SV::respect`、`RespectRules`）や生 `v` スキーマは、マッパブルフィールドに設定したアダプタに関わらず、内部的に RespectAdapter を使用します。
任意依存の `respect/validation` が必要です。

**OpisAdapter** は coercion なしの厳密な JSON Schema 意味論を適用します。
`"42"` のようなフォーム文字列は `type: integer` で不合格となります。`NativeAdapter` と `RespectAdapter` は同じ入力を受け入れます。

---

## 任意依存

既定の構成（NativeAdapter、NativeFileValidator、`SV::custom`）は外部パッケージが不要です。
両エンジンパッケージは composer の `suggest` に列挙されており、明示的にインストールしたときのみ読み込まれます。

- `respect/validation`: `RespectAdapter` と Respect エスケープハッチ（`RespectRules`、`SV::respect`、`postalCode`、`creditCard`、`iban`）および生 `v` スキーマを有効化します。
  Respect の factory は遅延初期化されるため、NativeAdapter を使う限り読み込まれません。

  ```
  composer require respect/validation
  ```

- `opis/json-schema`: `OpisAdapter` を有効化します。
  パッケージが未インストールの状態で使用しようとすると、分かりやすいエラーメッセージが表示されます。

  ```
  composer require opis/json-schema
  ```

---

## カスタムアダプタの書き方

```php
use SchemableValidator\Validation\BackendAdapter;
use SchemableValidator\Validation\ExecutableValidator;

final class MyAdapter implements BackendAdapter {
    public function compile(array $jsonSchema, ?MessageDict $dict = null): ExecutableValidator {
        // validate(array $data) は
        // [field => ['value' => ..., 'is_valid' => bool, 'errors' => ?string]]
        // を返す必要があります
        return new MyExecutableValidator(/* ... */);
    }
}

$validator = Validator::fromJsonSchema($jsonSchema, [], [], null, new MyAdapter());
```

バックエンド間でエラーメッセージを統一するには、エンジン固有の文字列をそのまま返すのではなく、失敗を中立ルール語彙に変換し `DefaultMessages` / `MessageDict::interpolate()` でテキストを解決してください。

---

## フロントエンドアダプタ

クライアントパッケージはネイティブ検証に加え、Zod と Valibot のアダプタを subpath export（`@uuki/schemable-validator-client/zod`、`/valibot`）として提供します。
Svelte や React Hook Form などのサードパーティアダプタも同じ JSON Schema と `x-*` のインターフェースを利用できます。
詳細は[クライアントアダプタのドキュメント](./client-adapter.md)を参照してください。

---

## `x-*` 拡張と `$vocabulary`

`x-when`、`x-custom-fields`、`x-transform`、インライン `errorMessage` などの拡張キーワードは、正式な JSON Schema `$vocabulary` には昇格せず `x-*` のまま維持します。

- `x-*` キーは汎用 JSON Schema バリデータがエラーなく無視するため、スキーマの可搬性が維持できます。
- `$vocabulary` への昇格は、外部の利用者が `x-when` や `x-custom-fields` を無視することで検証が不足する状況が実際に発生した場合に検討します。
- 昇格する場合は、少なくとも 1 メジャーバージョンの間 `x-*` 表記をエイリアスとして残します。

PHP バックエンドと FE 評価器の両方がこれらの拡張の意味論を直接実装しており、エンジン中立なメッセージ保証とクロススタックコンフォーマンススイートの基盤となっています。
