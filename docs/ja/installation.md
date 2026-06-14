# Installation

## Requirements

| | Version | 用途 |
|:--|:--|:--|
| PHP | ^7.4 \|\| ^8.x | core ライブラリ・WP プラグイン |
| WordPress | 5.9+ | WP プラグインのみ |
| Node.js | >=22.12.0 | `@uuki/schemable-validator-client` のみ |

## インストール方法

### PHP library

```shell
composer require uuki/schemable-validator
```

### プラグインパターン - WordPress

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

## 主要機能

### PHP library - 主要クラス

`composer require` でインストールすると、`SchemableValidator\` 名前空間以下のクラスが使えるようになります。

| クラス | 概要 |
|:--|:--|
| `Validator` | スキーマに対して入力値を検証する |
| `SV` | `SchemaBuilder` のファサード。`SV::object()` / `SV::string()` などでスキーマを構築する |
| `SchemaBuilder` | フィールドスキーマを組み立て、`Validator` または JSON Schema に変換する |
| `Template` | テンプレート文字列に検証済みデータを差し込む |
| `FormController` | マルチページフォームの検証済みデータをセッションで保持する |
| `MessageDict` | エラーメッセージをフィールド×ルール単位で定義する（i18n） |
| `Rules\FileExtension` | ファイルの MIME タイプを検証するカスタムルール |

詳細は [Feature Guide](/ja/feature-guide) および [SchemaBuilder](/ja/schema-builder) を参照してください。

### WordPress - グローバル関数

プラグインを有効化すると、以下の `schv_*` 関数がグローバルに使えるようになります。

| 関数 | 戻り値 | 概要 |
|:--|:--|:--|
| `schv_validator($schema, $options, $dict)` | `Validator` | バリデーターを生成する |
| `schv_message_dict()` | `MessageDict` | `schv_message_dict` フィルター経由でサイト全体の辞書を返す |
| `schv_form()` | `FormController` | マルチページフォームのセッション管理を行う |
| `schv_template($options)` | `Template` | WP オプションのテンプレートにデータを差し込む |
| `schv_register_schema($route, $provider)` | `void` | スキーマを REST エンドポイントとして登録する |
| `schv_schema_url($route)` | `string` | 登録済みスキーマの REST URL を返す |

詳細は [Feature Guide](/ja/feature-guide) および [Interfaces](/ja/interfaces) を参照してください。

## Package structure

```
packages/
  core/                          # コアライブラリ（フレームワーク非依存）
    Validator.php
    Template.php
    Controllers/FormController.php
    Interfaces/WordPress.php
    Rules/FileExtension.php
    Helpers/Security.php
    Helpers/Environment.php
  wp-schemable-validator/        # WordPress プラグイン
    index.php
    lib/core/                    # core の rsync コピー（composer 経由）
    src/Interfaces/WordPress/
      Plugin.php                 # 管理画面・設定登録
      helpers.php                # schv_* グローバル関数
    examples/                    # サンプルショートコード（ローカル開発用）
```
