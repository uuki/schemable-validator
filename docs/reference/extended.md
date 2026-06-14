# SV::file() / SV::respect() - Non-JSON Schema Types

These types perform server-side validation via Respect/Validation but cannot be converted to JSON Schema.
In `toJsonSchema()` output they are excluded from `properties`, and their field names are recorded in `x-unmapped-fields`.

The client's `validateObject` automatically skips these fields and defers them to the server.

---

## SV::file(accept) {#file}

Validates the MIME type of a file upload.

```php
SV::file(array $accept = [])
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$accept` | `string[]` | Array of allowed MIME types |

**Use case:** Restrict file types for `<input type="file">`.

```php
// Images only
SV::file(['image/jpeg', 'image/png', 'image/webp'])

// PDF only, optional input
SV::file(['application/pdf'])->optional()

// Any file type (existence check only)
SV::file()
```

### Server-side Usage

```php
$schema = SV::object([
  'name'   => SV::string()->min(1),
  'avatar' => SV::file(['image/jpeg', 'image/png'])->optional(),
]);

// Use validateFiles() to validate files
$result = $schema->toValidator()
  ->validate($_POST)
  ->validateFiles($_FILES)
  ->getResult();
```

### JSON Schema Output

`avatar` is not included in `properties` and is recorded in `x-unmapped-fields`.

```json
{
  "type": "object",
  "properties": {
    "name": { "type": "string", "minLength": 1 }
  },
  "required": ["name"],
  "x-unmapped-fields": ["avatar"]
}
```

---

## SV::postalCode(countryCode) {#postalcode}

Validates a **postal code** for a specific country. A shorthand that wraps Respect/Validation's `postalCode()` rule.

```php
SV::postalCode(string $countryCode)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$countryCode` | `string` | ISO 3166-1 alpha-2 country code (e.g. `'JP'`, `'US'`, `'DE'`) |

Cannot be expressed in JSON Schema; recorded in `x-unmapped-fields`.

```php
SV::postalCode('JP')->optional()  // Japanese postal code (optional)
SV::postalCode('US')              // US ZIP code
```

This is syntactic sugar for `SV::respect(v::postalCode('JP'))`.

---

## SV::creditCard(...brands) {#creditcard}

Validates a **credit card number** using the Luhn algorithm.

```php
SV::creditCard(string ...$brands)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `...$brands` | `string` | Card brands to accept (omit to accept all brands). E.g. `'Visa'`, `'Mastercard'` |

Cannot be expressed in JSON Schema; recorded in `x-unmapped-fields`.

```php
SV::creditCard()                    // All brands
SV::creditCard('Visa', 'Mastercard') // Visa / Mastercard only
```

---

## SV::iban() {#iban}

Validates an **IBAN** (International Bank Account Number).

```php
SV::iban()
```

Cannot be expressed in JSON Schema; recorded in `x-unmapped-fields`.

```php
SV::iban()->optional()
```

---

## SV::respect(rule) {#respect}

An escape hatch for specifying Respect/Validation rules directly. Use this for constraints that cannot be expressed with the built-in types.

```php
SV::respect(Respect\Validation\Validator $rule)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$rule` | `Respect\Validation\Validator` | A Respect validator instance |

**Use case:** Phone number validation with libphonenumber, IBAN validation, custom business rules — constraints that cannot be expressed in JSON Schema.

```php
use Respect\Validation\Validator as v;

// Built-in Respect credit card validation
SV::respect(v::creditCard())

// Inject custom logic via callback
SV::respect(v::callback(function ($value) {
  return strlen($value) === 8 && ctype_digit($value);
}))->optional()
```

### Integration with External Libraries

```php
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

$phoneUtil = PhoneNumberUtil::getInstance();

$schema = SV::object([
  'tel' => SV::respect(
    v::callback(function ($value) use ($phoneUtil) {
      try {
        $number = $phoneUtil->parse($value, 'JP');
        return $phoneUtil->isValidNumberForRegion($number, 'JP');
      } catch (NumberParseException) {
        return false;
      }
    })
  )->optional(),
]);
```

See [Advanced Usage](/reference/custom-validation) for more details.

### JSON Schema Output

```json
{
  "type": "object",
  "properties": {},
  "x-unmapped-fields": ["tel"]
}
```

---

## Handling x-unmapped-fields

To add client-side validation for `x-unmapped-fields`, use the client's `Constraint` or Zod's `.superRefine()`.

```typescript
// client: manually add validation
const result = validateObject(data, schema)
const unmapped = schema['x-unmapped-fields'] ?? []

if (unmapped.includes('tel')) {
  const ok = /^0\d{9,10}$/.test(data.tel ?? '')
  // extend result to add tel validation outcome
}
```

```typescript
// Zod: add via superRefine
const zodSchema = buildZodSchema(schema).extend({
  tel: z.string().optional().superRefine((val, ctx) => {
    if (!val) return
    if (!/^0\d{9,10}$/.test(val)) {
      ctx.addIssue({ code: 'custom', message: 'Please enter a valid phone number' })
    }
  }),
})
```
