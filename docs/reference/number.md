# SV::integer() / SV::number() - Number Types

Types for numeric input. Use `integer` for whole numbers only, and `number` when decimals are needed.

---

## SV::integer()

Validates integer values. String input from HTML forms is automatically coerced to an integer for evaluation.

```php
SV::integer()
```

**JSON Schema output:**
```json
{ "type": "integer" }
```

**Use case:** Age, quantity, rating score (integer), etc.

---

## SV::number()

Validates integers or decimals.

```php
SV::number()
```

**JSON Schema output:**
```json
{ "type": "number" }
```

**Use case:** Price, rating score (decimal), weight, etc.

---

## .min(n) {#min}

Sets the **minimum value**.

```php
SV::integer()->min(int $n)
SV::number()->min(int|float $n)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$n` | `int` (integer) / `int\|float` (number) | Minimum value allowed (inclusive) |

**JSON Schema keyword:** `minimum`

**Use case:** Non-negative quantity, rating score of at least 1.

```php
// Age of 0 or more
SV::integer()->min(0)->max(150)

// Rating score of 0.0 or more
SV::number()->min(0.0)->max(5.0)
```

```json
{ "type": "integer", "minimum": 0, "maximum": 150 }
```

---

## .max(n) {#max}

Sets the **maximum value**.

```php
SV::integer()->max(int $n)
SV::number()->max(int|float $n)
```

| Parameter | Type | Description |
|:--|:--|:--|
| `$n` | `int` (integer) / `int\|float` (number) | Maximum value allowed (inclusive) |

**JSON Schema keyword:** `maximum`

```php
// Integer score from 1 to 100
SV::integer()->min(1)->max(100)

// Decimal rating from 0.5 to 5.0
SV::number()->min(0.5)->max(5.0)
```

```json
{ "type": "number", "minimum": 0.5, "maximum": 5.0 }
```

---

## Combination Examples

```php
$schema = SV::object([
  // Age: integer 0–150, optional input
  'age'    => SV::integer()->min(0)->max(150)->optional(),

  // Score: number 0.0–5.0, optional input
  'score'  => SV::number()->min(0.0)->max(5.0)->optional(),

  // Quantity: at least 1
  'count'  => SV::integer()->min(1),
]);
```

```json
{
  "type": "object",
  "properties": {
    "age":   { "type": "integer", "minimum": 0, "maximum": 150 },
    "score": { "type": "number",  "minimum": 0, "maximum": 5   },
    "count": { "type": "integer", "minimum": 1 }
  },
  "required": ["count"]
}
```

> The client's `validateObject` converts form input strings to numbers using `Number()` before validation. The same applies to the Zod integration which uses `z.coerce.number()`.
