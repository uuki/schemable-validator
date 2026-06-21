# Interfaces

## WordPress Plugin

Adds a mail template settings page to the admin dashboard and manages templates via `get_option()`.

### Setup

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

A WP option named `SCHV_REPLY_FORMAT_FOR_{key}` is registered for each key in the argument.  
The settings page is displayed at **WP Admin › Settings › Schemable Validator**.

### Retrieving option keys

```php
$plugin = new Plugin([...]);
$keys = $plugin->keysAll();
// ['user' => 'SCHV_REPLY_FORMAT_FOR_user', 'admin' => 'SCHV_REPLY_FORMAT_FOR_admin']
```

### Integration with Template

Pass the option name (as a string) to the `templates` parameter of `schv_template()`.  
In a WordPress environment, the value is retrieved automatically via `get_option()`.

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

### Schema Editor

The Schema Editor admin page lets site operators define validation schemas without writing PHP.
Schemas are stored in two locations:

- **Theme directory** (`{active_theme}/schv-schemas/{slug}.json`) — version-controllable with git
- **wp_options** (`schv_schema_{slug}`) — backward-compatible fallback

`StoredSchemaProvider` reads the theme file first and falls back to `wp_options`.

#### Export and import

Each saved schema has an **Export** button that downloads the JSON file.
An **Import** form accepts a `.json` file and a target slug, writing to both the theme directory and `wp_options`.

#### Merge conflict detection

When a schema slug has code-defined fields registered via `schv_register_code_fields()`, the Schema Editor displays a warning listing any field names that overlap with the GUI definition.
On merge, the code-side definition takes precedence.

```php
// Register which fields your code defines for the "contact" schema.
// SchemaEditor will warn if the GUI also defines these fields.
schv_register_code_fields('contact', ['company_name', 'attachment']);
```

#### Cache control for schema endpoints

`schv_register_schema()` serves JSON Schema over REST with `Cache-Control: public, max-age=60, stale-while-revalidate=3600` and an ETag.
To force immediate invalidation in production, override the headers with the `schv_schema_cache_headers` filter:

```php
add_filter('schv_schema_cache_headers', function ($headers) {
    return ['Cache-Control' => 'no-cache, must-revalidate', 'ETag' => $headers['ETag']];
});
```

---

## WordPress Helper Functions

The following global functions become available once the plugin is activated.

| Function | Return | Description |
|:--|:--|:--|
| `schv_validator(array $schema, array $options = [], ?MessageDict $dict = null)` | `Validator` | Creates a Validator instance |
| `schv_message_dict()` | `MessageDict` | Returns the site-wide dictionary via the `schv_message_dict` filter |
| `schv_template(array $options = [])` | `Template` | Creates a Template instance |
| `schv_form()` | `FormController` | Creates a FormController instance |
| `schv_stored_schema(string $slug)` | `StoredSchemaProvider` | Loads a schema from the theme directory or `wp_options` |
| `schv_register_schema(string $route, SchemaProviderInterface $provider)` | `void` | Registers a REST endpoint serving JSON Schema |
| `schv_register_code_fields(string $slug, string[] $fields)` | `void` | Registers code-defined field names for merge conflict detection |

---

## Notes for WordPress Environments

### Conflicts between `$_REQUEST` and routing

WordPress uses `$_REQUEST` (a merge of GET and POST) for URL routing.  
If a form field name matches a WordPress reserved query variable (`name`, `p`, `page`, etc.),  
WordPress may look for a corresponding post on submission and return a 404.

**Workaround:** Use the `request` filter to remove the conflicting query variable on POST requests.

```php
add_filter('request', function ($qv) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['schv_action'] ?? '') === 'myform') {
    unset($qv['name']);
  }
  return $qv;
});
```

### `type="email"` and browser validation

`<input type="email">` triggers the browser's built-in validation,  
which may prevent form submission when an invalid value is entered.  
If you intend to validate on the server side, add `novalidate` to the form element.

```html
<form method="post" novalidate>
```

---

## AbstractInterface

Base interface class for custom environments.

```php
use SchemableValidator\Interfaces\AbstractInterface;

class MyInterface extends AbstractInterface {
  function __construct(array $templates) {
    // Fetch and transform templates in your own way, then pass them to the parent
    parent::__construct($templates);
  }
}
```

| Method | Description |
|:--|:--|
| `getTemplate(string $name): string` | Returns the template string for the given name |
| `getAll(): array` | Returns all templates as an array |
