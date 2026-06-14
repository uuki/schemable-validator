# Schema Reference

Lists all field definition expressions that can be passed to `SV::object()`.

Each expression maps to a JSON Schema draft 2020-12 keyword and is applied to both server-side validation (Respect/Validation) and client-side validation (client / Zod).

---

## Field Types

| Expression | JSON Schema `type` | Description |
|:--|:--|:--|
| [`SV::string()`](./string) | `"string"` | Text input. Supports length, format, and regex constraints |
| [`SV::integer()`](./number) | `"integer"` | Integer. Supports range constraints |
| [`SV::number()`](./number) | `"number"` | Integer or decimal. Supports range constraints |
| [`SV::boolean()`](./scalar) | `"boolean"` | Boolean value |
| [`SV::enum(values)`](./scalar) | `"string"` + `enum` | Select one from a set of choices |
| [`SV::array(items)`](./array) | `"array"` | Array. Accepts a schema for each element |
| [`SV::file(accept)`](./extended) | - (not JSON Schema) | File upload |
| [`SV::respect(rule)`](./extended) | - (not JSON Schema) | Direct Respect/Validation rule |
| [`SV::postalCode(country)`](./extended#postalcode) | - (not JSON Schema) | Country-specific postal code |
| [`SV::creditCard()`](./extended#creditcard) | - (not JSON Schema) | Credit card number (Luhn) |
| [`SV::iban()`](./extended#iban) | - (not JSON Schema) | IBAN |

## String Constraints

| Expression | JSON Schema keyword | Description |
|:--|:--|:--|
| [`.min(n)`](./string#min) | `minLength` | Minimum character count |
| [`.max(n)`](./string#max) | `maxLength` | Maximum character count |
| [`.email()`](./string#email) | `format: "email"` | Email address format |
| [`.url()`](./string#url) | `format: "uri"` | URL format |
| [`.pattern(p)`](./string#pattern) | `pattern` | Regular expression |
| [`.date()`](./string#date) | `format: "date"` | Date (YYYY-MM-DD) |
| [`.dateTime()`](./string#datetime) | `format: "date-time"` | Date-time (RFC 3339) |
| [`.time()`](./string#time) | `format: "time"` | Time (HH:mm:ss) |
| [`.uuid()`](./string#uuid) | `format: "uuid"` | UUID |
| [`.ipv4()`](./string#ipv4) | `format: "ipv4"` | IPv4 address |
| [`.ipv6()`](./string#ipv6) | `format: "ipv6"` | IPv6 address |
| [`.slug()`](./string#slug) | `pattern` | URL slug (lowercase alphanumeric and hyphens) |
| [`.domain()`](./string#domain) | `format: "hostname"` | Domain name |

## Array Constraints

| Expression | JSON Schema keyword | Description |
|:--|:--|:--|
| [`.minItems(n)`](./array#minitems) | `minItems` | Minimum number of items |
| [`.maxItems(n)`](./array#maxitems) | `maxItems` | Maximum number of items |

## Number Constraints

| Expression | JSON Schema keyword | Description |
|:--|:--|:--|
| [`.min(n)`](./number#min) | `minimum` | Minimum value |
| [`.max(n)`](./number#max) | `maximum` | Maximum value |

## Modifiers

| Expression | Effect |
|:--|:--|
| [`.optional()`](./modifiers#optional) | Excluded from the `required` array. Allows empty input |
| [`.nullable()`](./modifiers#nullable) | Extends `type` to `[type, "null"]`. Allows `null` values |

## Object & Output

| Expression | Description |
|:--|:--|
| [`SV::object(fields)`](./object#object) | Defines a set of fields |
| [`.when(field, expr, require)`](./object#when) | Conditional required |
| [`.toJsonSchema()`](./object#tojsonschema) | Output as JSON Schema (array) |
| [`.toJson()`](./object#tojson) | Output as JSON Schema (string) |
| [`.toValidator()`](./object#tovalidator) | Generate a Respect/Validation-based `Validator` |

## Condition Expressions (second argument of when())

Comparison expressions passed to `.when()`. Passing a scalar value directly is equivalent to `SV::equal()`.

| Expression | Operator | When to use |
|:--|:--|:--|
| [`SV::equal($value)`](./object#when-expressions) | `===` | When the value matches |
| [`SV::notEqual($value)`](./object#when-expressions) | `!==` | When the value does not match |
| [`SV::greaterThanOrEqual($n)`](./object#when-expressions) | `>=` | When the value is greater than or equal to $n |
| [`SV::lessThanOrEqual($n)`](./object#when-expressions) | `<=` | When the value is less than or equal to $n |
| [`SV::greaterThan($n)`](./object#when-expressions) | `>` | When the value is greater than $n |
| [`SV::lessThan($n)`](./object#when-expressions) | `<` | When the value is less than $n |
| [`SV::field('name')`](./object#when-expressions) | - | Use another field's value as the comparison target |
