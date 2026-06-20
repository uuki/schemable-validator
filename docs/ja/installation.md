# Installation

Schemable Validator は PHP コアライブラリ、WordPress プラグイン、TypeScript クライアントの3つのパッケージで構成されています。
必要なパッケージだけをインストールしてください。

## Requirements

| | Version | 用途 |
|:--|:--|:--|
| PHP | ^7.4 \|\| ^8.x | core ライブラリ、WP プラグイン |
| WordPress | 5.9+ | WP プラグインのみ |
| Node.js | >=22.12.0 | `@uuki/schemable-validator-client` のみ |

## PHP library

```shell
composer require uuki/schemable-validator-core
```

## WordPress プラグイン

リポジトリをクローンし、プラグインディレクトリに配置して依存パッケージをインストールします。

```shell
# リポジトリをクローン
git clone https://github.com/uuki/schemable-validator.git
# 設置先の例: wp-content/plugins/schemable-validator

# プラグイン内の WordPress パッケージに移動して依存をインストール
cd path/to/plugins/schemable-validator/packages/wp-schemable-validator
# 設置先が`wp-content`の場合: wp-content/plugins/schemable-validator/packages/wp-schemable-validator

composer install --no-dev
```

WordPress 管理画面のプラグイン一覧から **Schemable Validator** を有効化してください。

## インストール後に使えるもの

### コアクラス

`composer require` でインストールすると、`SchemableValidator\` 名前空間以下のクラスが使えるようになります。

| クラス | 名前空間 | 概要 |
|:--|:--|:--|
| `Validator` | `Orchestration` | スキーマに対して入力値を検証する |
| `SchemaBuilder` | `Orchestration` | フィールドスキーマを組み立て、`Validator` または JSON Schema に変換する |
| `Template` | `Orchestration` | テンプレート文字列に検証済みデータを差し込む |
| `SV` | *(ルート)* | `SchemaBuilder` のファサード。`SV::object()`、`SV::string()` などでスキーマを構築する |
| `CsrfGuard` | `Security` | CSRF トークンの生成と検証 |
| `FormController` | `Infrastructure` | マルチページフォームの検証済みデータをセッションで保持する |
| `MessageDict` | `I18n` | エラーメッセージをフィールドとルール単位で定義する（i18n） |
| `NativeFileValidator` | `Adapters\Native` | 依存なしのファイル MIME バリデーション（デフォルト） |
| `NativeImageDriver` | `Adapters\Native` | 画像サイズ、寸法の制約チェック |

::: info
既定のエンジン（`NativeAdapter`）は外部バリデーションライブラリなしで動作します。
`respect/validation` と `opis/json-schema` はオプション（`suggest`）依存です。
:::

詳細は [Feature Guide](/ja/feature-guide) および [SchemaBuilder](/ja/schema-builder) を参照してください。

### WordPress ヘルパー関数

プラグインを有効化すると、以下の `schv_*` 関数がグローバルに使えるようになります。

| 関数 | 戻り値 | 概要 |
|:--|:--|:--|
| `schv_validator($schema, $config)` | `Validator` | バリデーターを生成する（config でアダプタ、ドライバ、辞書を指定可能） |
| `schv_csrf()` | `CsrfGuard` | CSRF トークンマネージャーを生成する |
| `schv_message_dict()` | `MessageDict` | `schv_message_dict` フィルター経由でサイト全体の辞書を返す |
| `schv_form()` | `FormController` | マルチページフォームのセッション管理を行う |
| `schv_template($options)` | `Template` | WP オプションのテンプレートにデータを差し込む |
| `schv_register_schema($route, $provider)` | `void` | スキーマを REST エンドポイントとして登録する |
| `schv_schema_url($route)` | `string` | 登録済みスキーマの REST URL を返す |

詳細は [Feature Guide](/ja/feature-guide) および [Interfaces](/ja/interfaces) を参照してください。

## パッケージ構成

```
packages/
  core/                              # コアライブラリ（フレームワーク非依存）
    SV.php                           # ファサード
    constants.php

    Orchestration/
      Validator.php                  # バリデーションオーケストレータ
      SchemaBuilder.php              # スキーマ定義 → Validator / JSON Schema
      Template.php                   # テンプレート文字列補間

    Schema/                          # スキーマ定義層
      AbstractFieldSchema.php
      StringSchema.php
      IntegerSchema.php, NumberSchema.php
      BooleanSchema.php, EnumSchema.php
      ArraySchema.php, FileSchema.php
      CustomFieldSchema.php
      RuleMapper.php

    Validation/                      # インターフェース + 純ロジック（外部依存なし）
      BackendAdapter.php             # アダプターインターフェース
      ExecutableValidator.php        # フィールド単位の実行インターフェース
      CaptchaDriver.php             # CAPTCHA 検証インターフェース
      FileValidationDriver.php      # ファイル検証インターフェース
      ImageDriver.php               # 画像制約インターフェース
      CustomField.php               # エスケープハッチフィールドインターフェース
      Coercion.php, Formats.php     # Coercion Contract、フォーマット定義
      CalendarDate.php, JsonLogicEval.php
      Transform.php, MessageResolver.php

    Adapters/                        # 差し替え可能な実装
      Native/                        # デフォルト（外部依存ゼロ）
        NativeAdapter.php
        NativeExecutableValidator.php
        NativeFileValidator.php
        NativeImageDriver.php
      Respect/                       # オプション（respect/validation）
        RespectAdapter.php
        RespectExecutableValidator.php
        RespectRules.php
        Rules/                       # Respect AbstractRule 拡張
      Opis/                          # オプション（opis/json-schema）
        OpisAdapter.php
        OpisExecutableValidator.php
      Captcha/                       # CAPTCHA プロバイダドライバ
        AbstractCaptchaDriver.php
        ReCaptchaV3Driver.php
        HCaptchaDriver.php
        TurnstileDriver.php
        NullCaptchaDriver.php

    Infrastructure/
      CurlController.php             # SSRF 防御付き HTTPS クライアント
      FormController.php             # セッションベースのフォーム状態管理

    I18n/
      MessageDict.php
      DefaultMessages.php
      Locales/                       # ロケールメッセージファイル

    Security/
      CsrfGuard.php                  # CSRF トークン管理

  wp-schemable-validator/            # WordPress プラグイン
    index.php                        # プラグインブートストラップ
    composer.json                    # composer path リポジトリで core を参照
    src/Interfaces/WordPress/
      Plugin.php                     # 管理画面、設定登録
      helpers.php                    # schv_* グローバル関数
    examples/                        # サンプルショートコード（ローカル開発用）
```

::: info
`respect/validation` と `opis/json-schema` はオプション（`suggest`）依存です。
既定のエンジン（`NativeAdapter`）は外部バリデーションライブラリなしで動作します。
:::
