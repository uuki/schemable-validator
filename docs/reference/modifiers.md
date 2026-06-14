# Modifiers - optional / nullable

Modifiers can be applied to all field types (`string` / `integer` / `number` / `boolean` / `enum` / `file` / `respect`).

---

## .optional() {#optional}

Makes a field **optional**. The field is excluded from the JSON Schema `required` array.

```php
SV::string()->optional()
```

**Effects:**
- No longer included in the `required` array
- The client's `validateObject` skips constraint checks when the value is an empty string
- In Zod integration, treated as `z.preprocess(v => v === '' ? undefined : v, zType.optional())`

**Use case:** Fields that do not need to be filled in, such as phone number, company name, or comments.

```php
$schema = SV::object([
  'name'  => SV::string()->min(1)->max(100),           // required
  'email' => SV::string()->email(),                    // required
  'tel'   => SV::string()->pattern('^\d{10,11}$')->optional(),  // optional
]);
```

```json
{
  "properties": {
    "name":  { "type": "string", "minLength": 1, "maxLength": 100 },
    "email": { "type": "string", "format": "email" },
    "tel":   { "type": "string", "pattern": "^\\d{10,11}$" }
  },
  "required": ["name", "email"]
}
```

> `optional()` means "input is not required" — it does not mean "null is allowed". To allow null, combine it with `.nullable()`.

---

## .nullable() {#nullable}

**Adds `null` to the field's type.** Explicitly allows `null` values (or empty values).

```php
SV::string()->nullable()
```

**Effects:**
- The `type` in JSON Schema becomes an array such as `["string", "null"]`
- `null` is accepted as a valid value

**Use case:** Nullable database columns, explicitly representing an unset configuration value.

```php
SV::string()->url()->nullable()
```

```json
{ "type": ["string", "null"], "format": "uri" }
```

---

## Difference between optional and nullable

| | `optional()` | `nullable()` |
|:--|:--|:--|
| Meaning | Input is not required (can be omitted) | `null` can be sent as a value |
| Effect on `required` | Excluded from `required` | No effect |
| Empty string handling | Client skips validation | May be converted to `null` |
| JSON Schema | Excluded from `required` | `type: ["...", "null"]` |

### Using both together

To make a field both optional and nullable, apply both modifiers.

```php
SV::string()->url()->nullable()->optional()
```

```json
{
  "type": ["string", "null"],
  "format": "uri"
}
```

In this case the field is neither included in `required` nor does it reject `null` values.

---

## Combination Examples

```php
$schema = SV::object([
  'username' => SV::string()->min(3)->max(20),           // required, not nullable
  'bio'      => SV::string()->max(500)->optional(),      // optional, not nullable
  'website'  => SV::string()->url()->nullable()->optional(), // optional, nullable
  'age'      => SV::integer()->min(0)->max(150)->optional(), // optional number
]);
```
