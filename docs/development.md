# Development

## Requirements

- Node.js 22+ (v22.13.0 recommended at `~/.nvm_arm64`)
- PHP 8.x + Composer
- pnpm

## Setup

```sh
# 1. Install dependencies
composer install                          # core library
cd packages/wp-schemable-validator && composer install --no-dev

# 2. Install Node.js packages
pnpm install
```

## Local development (WP Playground)

```sh
# Switch to Node.js 22 (required to avoid the Int8Array bug)
export NVM_DIR=~/.nvm_arm64 && source ~/.nvm_arm64/nvm.sh && nvm use 22.13.0

cd playground
pnpm dev   # starts at http://127.0.0.1:9400
```

What `pnpm dev` does internally:

1. `sync-core` - rsync `packages/core/` to `packages/wp-schemable-validator/lib/core/`
2. `composer install --no-dev` - resolve plugin dependencies
3. `wp-playground-cli start` - launch WP Playground

### blueprint.json

`playground/blueprint.json` defines the PHP version, plugin activation, and initial settings.  
Initial email template values are also set in the `setSiteOptions` step.

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

### Sample pages

When `WP_ENVIRONMENT_TYPE === 'local'`, the plugin automatically creates sample pages.

| URL | Content |
|:--|:--|
| `/schv-validate/` | Basic validation for text fields |
| `/schv-contact/` | Regex schema, phone number, and select options |
| `/schv-files/` | File upload validation |
| `/schv-csrf/` | CSRF token generation and verification |
| `/schv-template/` | Email template placeholder expansion |
| `/schv-form-input/` | Multi-page form (input step) |
| `/schv-form-confirm/` | Multi-page form (confirmation step) |
| `/schv-form-complete/` | Multi-page form (completion step) |

When new pages are added on an existing site they are created incrementally via individual options such as `schv_contact_page_created` (see `setup.php`).

### Testing custom constraints in Playground

You can verify custom validation for `x-unmapped-fields` in the Playground by adding `superRefine()` to the Zod schema at `/schv-schema-client/`.  
Insert the following immediately after the `buildZodSchema()` call in `schema-client.php`.

```javascript
// Add the esm.sh import at the top of the script tag:
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

## E2E Tests (Playwright)

```sh
export NVM_DIR=~/.nvm_arm64 && source ~/.nvm_arm64/nvm.sh && nvm use 22.13.0
pnpm --filter @schemable-validator/e2e run test
```

### Test structure

| File | Tests | Coverage |
|:--|:--|:--|
| `tests/contact.spec.js` | 9 | Regex validation, phone number, type selection |
| `tests/csrf.spec.js` | 4 | CSRF token generation and verification |
| `tests/files.spec.js` | 4 | File upload validation |
| `tests/multipage.spec.js` | 6 | Multi-page form and session management |
| `tests/template.spec.js` | 4 | Email template expansion |
| `tests/validate.spec.js` | 5 | Basic validation and select options |

### How globalSetup works

`packages/e2e/globalSetup.js` runs the following before tests:

1. `sync-core` (rsync core → wp-schemable-validator/lib/core/)
2. `composer install --no-dev`
3. Spawn `wp-playground-cli start` (stdout pipe only)
4. Detect the `"Ready!"` banner from stdout
5. Poll `/` and `/schv-validate/` to confirm readiness

**Why Node.js 22 is required:**  
`@wp-playground/cli@3.x` crashes with an `Int8Array` error in the Node.js 20 WebStreams adapter. Node.js 22 resolves this.

### WP Playground-specific constraints

- **6 parallel workers**: Worker-to-worker session sharing is achieved by pointing the session storage to `/wordpress/wp-content/schv-sessions` on the NodeFS backend
- **PHP WASM session_start() bug**: `session_status()` incorrectly returns `PHP_SESSION_NONE` within the same request, so a `static bool $started` flag is used as a guard
- **`name` field 404**: Conflicts with WordPress `$_REQUEST` routing, so the `name` query variable is removed on POST via the `request` filter

---

## Directory structure

```
.
├── packages/
│   ├── core/                      # PHP core library
│   │   ├── Validator.php
│   │   ├── Template.php
│   │   ├── Controllers/
│   │   │   └── FormController.php
│   │   ├── Interfaces/
│   │   │   ├── AbstractInterface.php
│   │   │   └── WordPress.php
│   │   ├── Rules/
│   │   │   └── FileExtension.php   # Legacy (Respect dependency)
│   │   ├── Validation/
│   │   │   ├── BackendAdapter.php
│   │   │   ├── NativeExecutableValidator.php
│   │   │   ├── NativeFileValidator.php
│   │   │   ├── FileValidationDriver.php
│   │   │   ├── CustomField.php
│   │   │   ├── Formats.php
│   │   │   ├── Coercion.php
│   │   │   ├── JsonLogicEval.php
│   │   │   └── Adapters/
│   │   │       ├── RespectAdapter.php
│   │   │       ├── OpisAdapter.php
│   │   │       └── NativeAdapter.php
│   │   ├── I18n/
│   │   │   ├── MessageDict.php
│   │   │   ├── DefaultMessages.php
│   │   │   └── Locales/
│   │   ├── Drivers/Respect/
│   │   │   └── RespectRules.php
│   │   ├── Schema/
│   │   │   ├── CustomFieldSchema.php
│   │   │   └── meta-schema.json
│   │   └── Helpers/
│   │       ├── Security.php
│   │       └── Environment.php
│   ├── wp-schemable-validator/    # WordPress plugin
│   │   ├── index.php
│   │   ├── setup.php              # sample page generation for local use
│   │   ├── lib/core/              # rsync copy of core
│   │   ├── src/Interfaces/WordPress/
│   │   │   ├── Plugin.php         # admin screen and settings registration
│   │   │   └── helpers.php        # schv_* global functions
│   │   └── examples/              # shortcodes for local development
│   │       ├── loader.php
│   │       ├── validate.php
│   │       ├── contact.php
│   │       ├── files.php
│   │       ├── csrf.php
│   │       ├── template.php
│   │       └── multipage.php
│   └── e2e/                       # Playwright E2E tests
│       ├── playwright.config.js
│       ├── globalSetup.js
│       └── tests/
├── playground/                    # WP Playground configuration
│   ├── blueprint.json
│   ├── package.json
│   └── .nvmrc                     # 22.13.0
└── docs/
```
