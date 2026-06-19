# Installation

Schemable Validator ships as three independent packages — a PHP core library, a WordPress plugin, and a TypeScript client.
Install only the packages you need.

## Requirements

| | Version | Purpose |
|:--|:--|:--|
| PHP | ^7.4 \|\| ^8.x | Core library & WP plugin |
| WordPress | 5.9+ | WP plugin only |
| Node.js | >=22.12.0 | `@uuki/schemable-validator-client` only |

## PHP library

```shell
composer require uuki/schemable-validator
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

| Class | Description |
|:--|:--|
| `Validator` | Validates input values against a schema |
| `SV` | Facade for `SchemaBuilder`. Build schemas with `SV::object()`, `SV::string()`, etc. |
| `SchemaBuilder` | Assembles field schemas and converts them to `Validator` format or JSON Schema |
| `Template` | Injects validated data into template strings |
| `FormController` | Persists validated data across multi-page forms using sessions |
| `MessageDict` | Defines error messages per field × rule (i18n) |
| `Rules\FileExtension` | Custom rule that validates a file's MIME type (legacy; Respect/Validation dependency) |
| `NativeFileValidator` | Dependency-free file validation via `FileValidationDriver` (default) |

::: info
File validation uses `NativeFileValidator` via `FileValidationDriver` by default (no external dependencies). `Rules\FileExtension` is a legacy adapter that requires Respect/Validation.
:::

See [Feature Guide](/feature-guide) and [SchemaBuilder](/schema-builder) for details.

### WordPress helper functions

Once the plugin is activated, the following `schv_*` functions become available globally.

| Function | Return | Description |
|:--|:--|:--|
| `schv_validator($schema, $options, $dict)` | `Validator` | Creates a Validator instance |
| `schv_message_dict()` | `MessageDict` | Returns the site-wide dictionary via the `schv_message_dict` filter |
| `schv_form()` | `FormController` | Manages session state for multi-page forms |
| `schv_template($options)` | `Template` | Injects data into a WP option template |
| `schv_register_schema($route, $provider)` | `void` | Registers a schema as a REST endpoint |
| `schv_schema_url($route)` | `string` | Returns the REST URL of a registered schema |

See [Feature Guide](/feature-guide) and [Interfaces](/interfaces) for details.

## Package layout

```
packages/
  core/                          # Core library (framework-agnostic)
    Validator.php
    Template.php
    Controllers/FormController.php
    Interfaces/
      AbstractInterface.php
      WordPress.php
    Rules/FileExtension.php      # Legacy (Respect dependency)
    Validation/
      BackendAdapter.php         # Adapter interface
      ExecutableValidator.php
      NativeExecutableValidator.php
      NativeFileValidator.php    # Dependency-free file validation
      FileValidationDriver.php
      CustomField.php
      Formats.php
      Transform.php
      Coercion.php
      CalendarDate.php
      JsonLogicEval.php
      Adapters/
        RespectAdapter.php
        OpisAdapter.php
        NativeAdapter.php        # Default (dependency-free)
    I18n/
      MessageDict.php
      DefaultMessages.php
      Locales/                   # Locale message files
    Drivers/Respect/
      RespectRules.php
    Schema/
      CustomFieldSchema.php
      meta-schema.json
    Helpers/Security.php
    Helpers/Environment.php
  wp-schemable-validator/        # WordPress plugin
    index.php
    lib/core/                    # rsync copy of core (via composer)
    src/Interfaces/WordPress/
      Plugin.php                 # Admin screen & settings registration
      helpers.php                # schv_* global functions
    examples/                    # Sample shortcodes (for local development)
```

::: info
`respect/validation` and `opis/json-schema` are optional (`suggest`) dependencies. The default engine (`NativeAdapter`) works without any external validation library.
:::
