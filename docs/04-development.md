# Development

## Requirements

- Node.js 22+（`~/.nvm_arm64` に v22.13.0 推奨）
- PHP 8.x + Composer
- pnpm

## Setup

```sh
# 1. 依存パッケージをインストール
composer install                          # core ライブラリ
cd packages/wp-schemable-validator && composer install --no-dev

# 2. Node.js パッケージをインストール
pnpm install
```

## ローカル開発（WP Playground）

```sh
# Node.js 22 に切り替え（Int8Array バグ回避のため必須）
export NVM_DIR=~/.nvm_arm64 && source ~/.nvm_arm64/nvm.sh && nvm use 22.13.0

cd playground
pnpm dev   # http://127.0.0.1:9400 で起動
```

`pnpm dev` の内部処理:

1. `sync-core` — `packages/core/` を `packages/wp-schemable-validator/lib/core/` へ rsync
2. `composer install --no-dev` — プラグインの依存を解決
3. `wp-playground-cli start` — WP Playground を起動

### blueprint.json

`playground/blueprint.json` で PHP バージョン・プラグイン有効化・初期設定を定義している。  
メールテンプレートの初期値も `setSiteOptions` ステップで設定する。

```json
{
  "steps": [
    { "step": "defineWpConfigConsts", "consts": { "WP_ENVIRONMENT_TYPE": "local" } },
    { "step": "activatePlugin", "pluginPath": "wp-schemable-validator/index.php" },
    { "step": "setSiteOptions", "options": {
      "SCHV_REPLY_FORMAT_FOR_user":  "Dear {name},\nThank you.\n\n{body}",
      "SCHV_REPLY_FORMAT_FOR_admin": "From: {name} <{email}>\n\n{body}"
    }}
  ]
}
```

### サンプルページ

`WP_ENVIRONMENT_TYPE === 'local'` のとき、プラグインが自動でサンプルページを作成する。

| URL | 内容 |
|:--|:--|
| `/schv-validate/` | テキストフィールドの基本バリデーション |
| `/schv-contact/` | 正規表現スキーマ・電話番号・選択肢 |
| `/schv-files/` | ファイルアップロードのバリデーション |
| `/schv-csrf/` | CSRF トークンの生成・検証 |
| `/schv-template/` | メールテンプレートのプレースホルダー展開 |
| `/schv-form-input/` | マルチページフォーム（入力） |
| `/schv-form-confirm/` | マルチページフォーム（確認） |
| `/schv-form-complete/` | マルチページフォーム（完了） |

既存サイトでページが増えた場合は `schv_contact_page_created` 等の個別オプションで  
インクリメンタルに追加される（`setup.php` 参照）。

### Playground でカスタム Constraint を試す

`/schv-schema-sdk/` の Zod スキーマに `superRefine()` を追加することで、`x-unmapped-fields` のカスタム検証を Playground 上で動作確認できる。  
`schema-sdk.php` の `buildZodSchema()` 呼び出し直後に以下を挿入する。

```javascript
// esm.sh import を script タグ先頭に追加:
// import { isValidPhoneNumber } from 'https://esm.sh/libphonenumber-js@1'

zodSchema = buildZodSchema(jsonSchema).extend({
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!isValidPhoneNumber(val, 'JP')) {
      ctx.addIssue({ code: 'custom', message: '有効な日本の電話番号を入力してください' })
    }
  }),
})
```

---

## E2E テスト（Playwright）

```sh
export NVM_DIR=~/.nvm_arm64 && source ~/.nvm_arm64/nvm.sh && nvm use 22.13.0
pnpm --filter @schemable-validator/e2e run test
```

### テスト構成

| ファイル | テスト数 | 対象 |
|:--|:--|:--|
| `tests/contact.spec.js` | 9 | 正規表現バリデーション・電話番号・種別選択 |
| `tests/csrf.spec.js` | 4 | CSRF トークン生成・検証 |
| `tests/files.spec.js` | 4 | ファイルアップロード検証 |
| `tests/multipage.spec.js` | 6 | マルチページフォーム・セッション管理 |
| `tests/template.spec.js` | 4 | メールテンプレート展開 |
| `tests/validate.spec.js` | 5 | 基本バリデーション・選択肢 |

### globalSetup の仕組み

`packages/e2e/globalSetup.js` がテスト前に以下を実行する:

1. `sync-core`（core → wp-schemable-validator/lib/core/ rsync）
2. `composer install --no-dev`
3. `wp-playground-cli start` を spawn（stdout のみ pipe）
4. stdout から `"Ready!"` バナーを検出
5. `/` と `/schv-validate/` をポーリングして準備完了を確認

**Node.js 22 が必須な理由:**  
`@wp-playground/cli@3.x` は Node.js 20 の WebStreams アダプターで  
`Int8Array` エラーを起こしクラッシュする。Node.js 22 で解消。

### WP Playground 固有の制約

- **6 ワーカー並列**: セッションの保存先を NodeFS バックエンドの  
  `/wordpress/wp-content/schv-sessions` に向けることでワーカー間共有を実現
- **PHP WASM の session_start() バグ**: 同一リクエスト内で `session_status()` が  
  `PHP_SESSION_NONE` を誤返却するため、`static bool $started` フラグで防御
- **`name` フィールドの 404**: WordPress の `$_REQUEST` ルーティングと衝突するため  
  `request` フィルターで POST 時に `name` クエリ変数を除去

---

## ディレクトリ構成

```
.
├── packages/
│   ├── core/                      # PHP コアライブラリ
│   │   ├── Validator.php
│   │   ├── Template.php
│   │   ├── Controllers/
│   │   │   └── FormController.php
│   │   ├── Interfaces/
│   │   │   ├── AbstractInterface.php
│   │   │   └── WordPress.php
│   │   ├── Rules/
│   │   │   └── FileExtension.php
│   │   └── Helpers/
│   │       ├── Security.php
│   │       └── Environment.php
│   ├── wp-schemable-validator/    # WordPress プラグイン
│   │   ├── index.php
│   │   ├── setup.php              # ローカル用サンプルページ生成
│   │   ├── lib/core/              # core の rsync コピー
│   │   ├── src/Interfaces/WordPress/
│   │   │   ├── Plugin.php         # 管理画面・設定登録
│   │   │   └── helpers.php        # schv_* グローバル関数
│   │   └── examples/              # ローカル開発用ショートコード
│   │       ├── loader.php
│   │       ├── validate.php
│   │       ├── contact.php
│   │       ├── files.php
│   │       ├── csrf.php
│   │       ├── template.php
│   │       └── multipage.php
│   └── e2e/                       # Playwright E2E テスト
│       ├── playwright.config.js
│       ├── globalSetup.js
│       └── tests/
├── playground/                    # WP Playground 設定
│   ├── blueprint.json
│   ├── package.json
│   └── .nvmrc                     # 22.13.0
└── docs/
```
