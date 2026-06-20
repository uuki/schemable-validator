# Installation

Schemable Validator ships as three independent packages: a PHP core library, a WordPress plugin, and a TypeScript client.
Install only the packages you need.

## Requirements

| | Version | Purpose |
|:--|:--|:--|
| PHP | ^7.4 \|\| ^8.x | Core library & WP plugin |
| WordPress | 5.9+ | WP plugin only |
| Node.js | >=22.12.0 | `@uuki/schemable-validator-client` only |

## PHP library

```shell
composer require uuki/schemable-validator-core
```

## WordPress plugin

Clone the repository, place it in your plugins directory, and install the dependencies.

```shell
# Clone the repository
git clone https://github.com/uuki/schemable-validator.git
# Example destination: wp-content/plugins/schemable-validator

# Navigate to the WordPress package inside the plugin and install dependencies
cd path/to/plugins/schemable-validator/packages/wp-schemable-validator
# If placed under `wp-content`: wp-content/plugins/schemable-validator/packages/wp-schemable-validator

composer install --no-dev
```

Activate **Schemable Validator** from the plugin list in the WordPress admin dashboard.

## What's included

### Core classes

After installing via `composer require`, the classes under the `SchemableValidator\` namespace become available.

| Class | Namespace | Description |
|:--|:--|:--|
| `Validator` | `Orchestration` | Validates input values against a schema |
| `SchemaBuilder` | `Orchestration` | Assembles field schemas and converts them to `Validator` format or JSON Schema |
| `Template` | `Orchestration` | Injects validated data into template strings |
| `SV` | *(root)* | Facade for `SchemaBuilder`. Build schemas with `SV::object()`, `SV::string()`, etc. |
| `CsrfGuard` | `Security` | CSRF token generation and verification |
| `FormController` | `Infrastructure` | Persists validated data across multi-page forms using sessions |
| `MessageDict` | `I18n` | Defines error messages per field and rule (i18n) |
| `NativeFileValidator` | `Adapters\Native` | Dependency-free file MIME validation (default) |
| `NativeImageDriver` | `Adapters\Native` | Image dimension and size constraint checks |

::: info
The default engine (`NativeAdapter`) works without any external validation library.
`respect/validation` and `opis/json-schema` are optional (`suggest`) dependencies.
:::

See [Feature Guide](/feature-guide) and [SchemaBuilder](/schema-builder) for details.

### WordPress helper functions

Once the plugin is activated, the following `schv_*` functions become available globally.

| Function | Return | Description |
|:--|:--|:--|
| `schv_validator($schema, $config)` | `Validator` | Creates a Validator instance with optional config (adapter, drivers, dict) |
| `schv_csrf()` | `CsrfGuard` | Creates a CSRF token manager |
| `schv_message_dict()` | `MessageDict` | Returns the site-wide dictionary via the `schv_message_dict` filter |
| `schv_form()` | `FormController` | Manages session state for multi-page forms |
| `schv_template($options)` | `Template` | Injects data into a WP option template |
| `schv_register_schema($route, $provider)` | `void` | Registers a schema as a REST endpoint |
| `schv_schema_url($route)` | `string` | Returns the REST URL of a registered schema |

See [Feature Guide](/feature-guide) and [Interfaces](/interfaces) for details.

## Package layout

```
packages/
  core/                              # Core library (framework-agnostic)
    SV.php                           # Facade
    constants.php

    Orchestration/
      Validator.php                  # Validation orchestrator
      SchemaBuilder.php              # Schema definition → Validator / JSON Schema
      Template.php                   # Template string interpolation

    Schema/                          # Schema definition layer
      AbstractFieldSchema.php
      StringSchema.php
      IntegerSchema.php, NumberSchema.php
      BooleanSchema.php, EnumSchema.php
      ArraySchema.php, FileSchema.php
      CustomFieldSchema.php
      RuleMapper.php

    Validation/                      # Interfaces + pure logic (no external deps)
      BackendAdapter.php             # Adapter interface
      ExecutableValidator.php        # Per-field executor interface
      CaptchaDriver.php             # CAPTCHA verification interface
      FileValidationDriver.php      # File validation interface
      ImageDriver.php               # Image constraint interface
      CustomField.php               # Escape-hatch field interface
      Coercion.php, Formats.php     # Coercion Contract & format definitions
      CalendarDate.php, JsonLogicEval.php
      Transform.php, MessageResolver.php

    Adapters/                        # Swappable implementations
      Native/                        # Default (zero external deps)
        NativeAdapter.php
        NativeExecutableValidator.php
        NativeFileValidator.php
        NativeImageDriver.php
      Respect/                       # Optional (respect/validation)
        RespectAdapter.php
        RespectExecutableValidator.php
        RespectRules.php
        Rules/                       # Respect AbstractRule extensions
      Opis/                          # Optional (opis/json-schema)
        OpisAdapter.php
        OpisExecutableValidator.php
      Captcha/                       # CAPTCHA provider drivers
        AbstractCaptchaDriver.php
        ReCaptchaV3Driver.php
        HCaptchaDriver.php
        TurnstileDriver.php
        NullCaptchaDriver.php

    Infrastructure/
      CurlController.php             # SSRF-guarded HTTPS client
      FormController.php             # Session-based form state

    I18n/
      MessageDict.php
      DefaultMessages.php
      Locales/                       # Locale message files

    Security/
      CsrfGuard.php                  # CSRF token management

  wp-schemable-validator/            # WordPress plugin
    index.php                        # Plugin bootstrap
    composer.json                    # Requires core via composer path repo
    src/Interfaces/WordPress/
      Plugin.php                     # Admin screen & settings
      helpers.php                    # schv_* global functions
    examples/                        # Sample shortcodes (local dev)
```

::: info
`respect/validation` and `opis/json-schema` are optional (`suggest`) dependencies.
The default engine (`NativeAdapter`) works without any external validation library.
:::
