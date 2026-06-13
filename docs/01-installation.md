# Installation

## Requirements

| | Version | 用途 |
|:--|:--|:--|
| PHP | ^7.4 \|\| ^8.x | core ライブラリ・WP プラグイン |
| WordPress | 5.9+ | WP プラグインのみ |
| Node.js | >=22.12.0 | `@schemable-validator/client` のみ |

## PHP library

```shell
composer require uuki/schemable-validator:0.9.0
```

## WordPress plugin

リポジトリをクローンし、プラグインディレクトリに配置して依存パッケージをインストールします。

```shell
# リポジトリをプラグインディレクトリに直接クローン
git clone https://github.com/uuki/schemable-validator.git \
  wp-content/plugins/schemable-validator

# プラグイン内の WordPress パッケージに移動して依存をインストール
cd wp-content/plugins/schemable-validator/packages/wp-schemable-validator
composer install --no-dev
```

WordPress 管理画面のプラグイン一覧から **Schemable Validator** を有効化してください。

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
