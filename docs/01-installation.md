# Installation

## Requirements

| | Version |
|:--|:--|
| PHP | ^7.4 \|\| ^8.x |
| Node.js | ^22 (playground / E2E のみ) |

## PHP library

```shell
composer require uuki/schemable-validator:0.x@dev
```

## WordPress plugin

`packages/wp-schemable-validator` をプラグインとして配置する。
依存パッケージは Composer でインストールする。

```shell
cd packages/wp-schemable-validator
composer install --no-dev
```

`wp-content/plugins/wp-schemable-validator/index.php` としてマウントすれば有効化できる。

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
    examples/                    # ローカル開発用サンプルページ
```
