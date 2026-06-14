# SV::boolean() / SV::enum() - Boolean and Enum Types

---

## SV::boolean()

Validates boolean values. In addition to `true` / `false`, also accepts `"1"` / `"0"` / `"on"` / `"off"` / `"yes"` / `"no"` as form input.

```php
SV::boolean()
```

**JSON Schema output:**
```json
{ "type": "boolean" }
```

**Use case:** Terms of service agreement checkbox, flag input.

```php
$schema = SV::object([
  'agreement' => SV::boolean(),
  'newsletter' => SV::boolean()->optional(),
]);
```

```json
{
  "properties": {
    "agreement":  { "type": "boolean" },
    "newsletter": { "type": "boolean" }
  },
  "required": ["agreement"]
}
```

> Because HTML `<input type="checkbox">` does not submit a value when unchecked, handle it server-side as `$_POST['agreement'] ?? ''`.

---

## SV::enum(values)

Validates that the value is one of the defined choices.

```php
SV::enum(array $values)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$values` | `string[]` | Array of allowed strings |

**JSON Schema output:**
```json
{ "type": "string", "enum": ["a", "b", "c"] }
```

**Use case:** `<select>` or radio button choices. Validating categorical values stored in a database.

```php
// Inquiry type
SV::enum(['general', 'support', 'sales', 'other'])

// Optional status selection
SV::enum(['draft', 'published', 'archived'])->optional()
```

```json
{ "type": "string", "enum": ["general", "support", "sales", "other"] }
```

### Note: Handling empty strings

If a `<select>` element has a "Please select" option with `value=""`, adding `.optional()` causes the empty string to skip validation. Do not use `.optional()` if selection is required.

```php
// Empty string not allowed (selection required)
SV::enum(['general', 'support', 'other'])

// Empty string (no selection) is allowed
SV::enum(['general', 'support', 'other'])->optional()
```

---

## Combination Examples

```php
$schema = SV::object([
  'type'      => SV::enum(['question', 'feedback', 'bug']),
  'priority'  => SV::enum(['low', 'medium', 'high'])->optional(),
  'published' => SV::boolean(),
]);
```
